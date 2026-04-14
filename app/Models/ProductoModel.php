<?php
namespace App\Models;
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
