<?php
namespace App\Models;
use App\DTOs\ProductDTO;
use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre', 'marca_id', 'precio', 'precio_oferta', 'costo', 'stock_general', 'proveedor_id'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'nombre' => 'required|max_length[200]',
        'precio' => 'required|decimal|greater_than_equal_to[0]',
        'costo' => 'required|decimal|greater_than_equal_to[0]',
        'stock_general' => 'required|integer|greater_than_equal_to[0]',
    ];

    public function getWithRelations(): array
    {
        return $this->select('productos.*, marcas.nombre as marca_nombre, proveedores.nombre as proveedor_nombre')
            ->join('marcas', 'marcas.id = productos.marca_id', 'left')
            ->join('proveedores', 'proveedores.id = productos.proveedor_id', 'left')
            ->findAll();
    }

    public function getOneWithRelations(int $id): ?array
    {
        return $this->select('productos.*, marcas.nombre as marca_nombre, proveedores.nombre as proveedor_nombre')
            ->join('marcas', 'marcas.id = productos.marca_id', 'left')
            ->join('proveedores', 'proveedores.id = productos.proveedor_id', 'left')
            ->where('productos.id', $id)
            ->first();
    }

    public function getCategorias(int $productoId): array
    {
        $db = \Config\Database::connect();
        return $db->table('producto_categoria pc')
            ->select('c.*')
            ->join('categorias c', 'c.id = pc.categoria_id')
            ->where('pc.producto_id', $productoId)
            ->get()->getResultArray();
    }

    public function getTiendas(int $productoId): array
    {
        $db = \Config\Database::connect();
        return $db->table('producto_tienda pt')
            ->select('t.*, pt.valor_especifico, pt.valor_oferta_esp, pt.stock_especifico, pt.external_id')
            ->join('tiendas t', 't.id = pt.tienda_id')
            ->where('pt.producto_id', $productoId)
            ->get()->getResultArray();
    }

    public function getAllProductoTiendas(): array
    {
        $db = \Config\Database::connect();
        return $db->table('producto_tienda')
            ->select('producto_id, tienda_id, stock_especifico')
            ->get()->getResultArray();
    }

    public function syncCategorias(int $productoId, array $categoriaIds): void
    {
        $db = \Config\Database::connect();
        $db->table('producto_categoria')->where('producto_id', $productoId)->delete();
        foreach ($categoriaIds as $catId) {
            $db->table('producto_categoria')->insert([
                'producto_id' => $productoId,
                'categoria_id' => $catId,
            ]);
        }
    }

    /**
     * Upsert de tiendas: actualiza si ya existe el registro, inserta si no.
     * Esto preserva el external_id que el sistema guardó automáticamente,
     * a menos que el formulario envíe uno explícitamente.
     */
    public function syncTiendas(int $productoId, array $tiendaData): void
    {
        $db = \Config\Database::connect();

        $enabledIds = [];

        foreach ($tiendaData as $td) {
            if (empty($td['enabled'])) {
                continue;
            }

            $tiendaId = (int)$td['tienda_id'];
            $enabledIds[] = $tiendaId;

            $existing = $db->table('producto_tienda')
                ->where('producto_id', $productoId)
                ->where('tienda_id', $tiendaId)
                ->get()->getRowArray();

            $payload = [
                'valor_especifico' => $td['valor_especifico'] ?: null,
                'valor_oferta_esp' => $td['valor_oferta_esp'] ?: null,
                'stock_especifico' => $td['stock_especifico'] ?: null,
            ];

            // Solo sobreescribe external_id si el formulario envió un valor explícito
            if (isset($td['external_id']) && $td['external_id'] !== '') {
                $payload['external_id'] = $td['external_id'];
            }

            if ($existing) {
                $db->table('producto_tienda')
                    ->where('producto_id', $productoId)
                    ->where('tienda_id', $tiendaId)
                    ->update($payload);
            } else {
                $payload['producto_id'] = $productoId;
                $payload['tienda_id']   = $tiendaId;
                if (!isset($payload['external_id'])) {
                    $payload['external_id'] = null;
                }
                $db->table('producto_tienda')->insert($payload);
            }
        }

        // Eliminar tiendas que quedaron deshabilitadas
        if (!empty($enabledIds)) {
            $db->table('producto_tienda')
                ->where('producto_id', $productoId)
                ->whereNotIn('tienda_id', $enabledIds)
                ->delete();
        } else {
            $db->table('producto_tienda')
                ->where('producto_id', $productoId)
                ->delete();
        }
    }

    /**
     * Crea o actualiza el producto en el catálogo interno a partir de un ProductDTO.
     *
     * Estrategia de matching (en orden):
     *   1. Por external_id en producto_tienda del tienda Jumpseller (más confiable)
     *   2. Por nombre exacto en productos
     *   3. Si no existe: crea uno nuevo
     *
     * Siempre deja el producto habilitado en la tienda Jumpseller con su external_id.
     * Precios y stock del DTO se usan como valores base del sistema.
     *
     * Devuelve el producto_id interno.
     */
    public function upsertFromDto(ProductDTO $dto, int $jumpsellerTiendaId): int
    {
        $db = \Config\Database::connect();
        $productoId = null;

        // 1. Buscar por external_id en producto_tienda (Jumpseller)
        if ($dto->sourceId && $jumpsellerTiendaId) {
            $row = $db->table('producto_tienda')
                ->where('tienda_id', $jumpsellerTiendaId)
                ->where('external_id', (string)$dto->sourceId)
                ->get()->getRowArray();
            if ($row) {
                $productoId = (int)$row['producto_id'];
            }
        }

        // 2. Buscar por nombre exacto
        if (!$productoId && $dto->name) {
            $existing = $this->where('nombre', mb_substr($dto->name, 0, 200))->first();
            if ($existing) {
                $productoId = (int)$existing['id'];
            }
        }

        // Datos base del producto — precios y stock vienen de Jumpseller como referencia general
        $productoData = [
            'nombre'        => mb_substr($dto->name, 0, 200),
            'precio'        => (float)$dto->regularPrice,
            'precio_oferta' => $dto->salePrice !== '' ? (float)$dto->salePrice : null,
            'costo'         => 0,
            'stock_general' => $dto->stockQuantity,
            'marca_id'      => null,
            'proveedor_id'  => null,
        ];

        if ($productoId) {
            $this->update($productoId, $productoData);
        } else {
            $this->insert($productoData);
            $productoId = $this->getInsertID();
        }

        // Habilitar en tienda Jumpseller y guardar su external_id
        if ($jumpsellerTiendaId && $dto->sourceId) {
            $exists = $db->table('producto_tienda')
                ->where('producto_id', $productoId)
                ->where('tienda_id', $jumpsellerTiendaId)
                ->countAllResults() > 0;

            if ($exists) {
                $db->table('producto_tienda')
                    ->where('producto_id', $productoId)
                    ->where('tienda_id', $jumpsellerTiendaId)
                    ->update(['external_id' => (string)$dto->sourceId]);
            } else {
                $db->table('producto_tienda')->insert([
                    'producto_id'      => $productoId,
                    'tienda_id'        => $jumpsellerTiendaId,
                    'external_id'      => (string)$dto->sourceId,
                    'valor_especifico' => null,
                    'valor_oferta_esp' => null,
                    'stock_especifico' => null,
                ]);
            }
        }

        return $productoId;
    }

    /**
     * Guarda el ID externo de un producto en una tienda específica.
     * Llamado por el sistema de migración/sync tras crear o actualizar
     * el producto en la plataforma destino.
     */
    public function setExternalId(int $productoId, int $tiendaId, string $externalId): void
    {
        $db = \Config\Database::connect();

        $exists = $db->table('producto_tienda')
            ->where('producto_id', $productoId)
            ->where('tienda_id', $tiendaId)
            ->countAllResults() > 0;

        if ($exists) {
            $db->table('producto_tienda')
                ->where('producto_id', $productoId)
                ->where('tienda_id', $tiendaId)
                ->update(['external_id' => $externalId]);
        }
    }
}
