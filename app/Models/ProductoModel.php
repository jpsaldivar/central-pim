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
            ->select('t.*, pt.valor_especifico, pt.valor_oferta_esp, pt.stock_especifico')
            ->join('tiendas t', 't.id = pt.tienda_id')
            ->where('pt.producto_id', $productoId)
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

    public function syncTiendas(int $productoId, array $tiendaData): void
    {
        $db = \Config\Database::connect();
        $db->table('producto_tienda')->where('producto_id', $productoId)->delete();
        foreach ($tiendaData as $td) {
            if (!empty($td['enabled'])) {
                $db->table('producto_tienda')->insert([
                    'producto_id'      => $productoId,
                    'tienda_id'        => $td['tienda_id'],
                    'valor_especifico' => $td['valor_especifico'] ?: null,
                    'valor_oferta_esp' => $td['valor_oferta_esp'] ?: null,
                    'stock_especifico' => $td['stock_especifico'] ?: null,
                ]);
            }
        }
    }
}
