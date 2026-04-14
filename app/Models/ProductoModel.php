<?php
namespace App\Models;
use App\DTOs\ProductDTO;
use CodeIgniter\Model;

class ProductoModel extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['sku', 'nombre', 'marca_id', 'precio', 'precio_oferta', 'costo', 'stock_general', 'proveedor_id'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'nombre'        => 'required|max_length[200]',
        'sku'           => 'permit_empty|max_length[100]|is_unique[productos.sku,id,{id}]',
        'precio'        => 'required|decimal|greater_than_equal_to[0]',
        'costo'         => 'required|decimal|greater_than_equal_to[0]',
        'stock_general' => 'required|integer|greater_than_equal_to[0]',
    ];

    public function getWithRelations(int $perPage = 25, string $searchField = '', string $searchValue = ''): array
    {
        $builder = $this->select('productos.*, marcas.nombre as marca_nombre, proveedores.nombre as proveedor_nombre')
            ->join('marcas', 'marcas.id = productos.marca_id', 'left')
            ->join('proveedores', 'proveedores.id = productos.proveedor_id', 'left');

        if ($searchValue !== '') {
            $columnMap = [
                'sku'       => 'productos.sku',
                'nombre'    => 'productos.nombre',
                'marca'     => 'marcas.nombre',
                'proveedor' => 'proveedores.nombre',
            ];
            $col = $columnMap[$searchField] ?? 'productos.nombre';
            $builder->like($col, $searchValue, 'both');
        }

        return $builder->paginate($perPage);
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

    public function getAllProductoTiendas(array $productoIds = []): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('producto_tienda')
            ->select('producto_id, tienda_id, stock_especifico, valor_especifico, valor_oferta_esp');
        if (!empty($productoIds)) {
            $builder->whereIn('producto_id', $productoIds);
        }
        return $builder->get()->getResultArray();
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

        // 2. Buscar por SKU
        if (!$productoId && $dto->sku) {
            $existing = $this->where('sku', $dto->sku)->first();
            if ($existing) {
                $productoId = (int)$existing['id'];
            }
        }

        // 3. Buscar por nombre exacto
        if (!$productoId && $dto->name) {
            $existing = $this->where('nombre', mb_substr($dto->name, 0, 200))->first();
            if ($existing) {
                $productoId = (int)$existing['id'];
            }
        }

        // Datos base del producto — precios y stock vienen de Jumpseller como referencia general
        $productoData = [
            'sku'           => $dto->sku ?: null,
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
     * Actualiza la marca de un conjunto de productos de forma masiva.
     * Devuelve el número de filas afectadas.
     */
    public function bulkUpdateMarca(array $ids, ?int $marcaId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $db = \Config\Database::connect();
        $db->table('productos')
            ->whereIn('id', $ids)
            ->update(['marca_id' => $marcaId]);
        return $db->affectedRows();
    }

    /**
     * Actualiza masivamente precios generales y específicos por tienda.
     * Solo actualiza producto_tienda si el registro ya existe (producto activo en esa tienda).
     * Valor vacío en precio_oferta → NULL (elimina oferta).
     * Devuelve el número de productos actualizados.
     */
    public function bulkUpdatePrecios(
        array $precios,
        array $preciosOferta,
        array $valoresEsp,
        array $valoresOfertaEsp
    ): int {
        $db    = \Config\Database::connect();
        $count = 0;

        foreach ($precios as $id => $precio) {
            $id = (int)$id;
            if (!$id) continue;

            $data = [];
            if ($precio !== '') {
                $data['precio'] = (float)str_replace(',', '.', $precio);
            }
            $oferta = $preciosOferta[$id] ?? '';
            $data['precio_oferta'] = $oferta !== '' ? (float)str_replace(',', '.', $oferta) : null;

            if (!empty($data)) {
                $db->table('productos')->where('id', $id)->update($data);
                $count++;
            }

            // Específico por tienda
            $allTiendaIds = array_unique(array_merge(
                array_keys($valoresEsp[$id] ?? []),
                array_keys($valoresOfertaEsp[$id] ?? [])
            ));
            foreach ($allTiendaIds as $tiendaId) {
                $tiendaId  = (int)$tiendaId;
                $tiendaRow = [];
                if (isset($valoresEsp[$id][$tiendaId])) {
                    $v = $valoresEsp[$id][$tiendaId];
                    $tiendaRow['valor_especifico'] = $v !== '' ? (float)str_replace(',', '.', $v) : null;
                }
                if (isset($valoresOfertaEsp[$id][$tiendaId])) {
                    $v = $valoresOfertaEsp[$id][$tiendaId];
                    $tiendaRow['valor_oferta_esp'] = $v !== '' ? (float)str_replace(',', '.', $v) : null;
                }
                if (!empty($tiendaRow)) {
                    $db->table('producto_tienda')
                        ->where('producto_id', $id)
                        ->where('tienda_id', $tiendaId)
                        ->update($tiendaRow);
                }
            }
        }
        return $count;
    }

    /**
     * Actualiza masivamente stock general y específico por tienda.
     * Valor vacío → NULL en stock_especifico (usa el general).
     * Devuelve el número de productos actualizados.
     */
    public function bulkUpdateStock(array $stocks, array $stocksEsp): int
    {
        $db    = \Config\Database::connect();
        $count = 0;

        foreach ($stocks as $id => $stock) {
            $id = (int)$id;
            if (!$id) continue;

            if ($stock !== '') {
                $db->table('productos')
                    ->where('id', $id)
                    ->update(['stock_general' => (int)$stock]);
                $count++;
            }

            foreach (($stocksEsp[$id] ?? []) as $tiendaId => $stockEsp) {
                $db->table('producto_tienda')
                    ->where('producto_id', $id)
                    ->where('tienda_id', (int)$tiendaId)
                    ->update(['stock_especifico' => $stockEsp !== '' ? (int)$stockEsp : null]);
            }
        }
        return $count;
    }

    /**
     * Agrega una categoría a un conjunto de productos (sin eliminar las existentes).
     * Si el par producto/categoría ya existe, lo omite.
     * Devuelve el número de inserciones realizadas.
     */
    public function bulkAddCategoria(array $ids, int $categoriaId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $db      = \Config\Database::connect();
        $count   = 0;
        foreach ($ids as $productoId) {
            $exists = $db->table('producto_categoria')
                ->where('producto_id', $productoId)
                ->where('categoria_id', $categoriaId)
                ->countAllResults() > 0;
            if (!$exists) {
                $db->table('producto_categoria')->insert([
                    'producto_id'  => $productoId,
                    'categoria_id' => $categoriaId,
                ]);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Activa un conjunto de productos en una tienda específica.
     * Si el registro ya existe, lo omite (no toca stock ni precios).
     * Devuelve el número de productos habilitados por primera vez.
     */
    public function bulkActivarEnTienda(array $ids, int $tiendaId): int
    {
        if (empty($ids)) {
            return 0;
        }
        $db    = \Config\Database::connect();
        $count = 0;
        foreach ($ids as $productoId) {
            $exists = $db->table('producto_tienda')
                ->where('producto_id', $productoId)
                ->where('tienda_id', $tiendaId)
                ->countAllResults() > 0;
            if (!$exists) {
                $db->table('producto_tienda')->insert([
                    'producto_id'      => $productoId,
                    'tienda_id'        => $tiendaId,
                    'valor_especifico' => null,
                    'valor_oferta_esp' => null,
                    'stock_especifico' => null,
                    'external_id'      => null,
                ]);
                $count++;
            }
        }
        return $count;
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
