<?php
namespace App\Controllers;

use App\Models\ProductoModel;
use App\Models\MarcaModel;
use App\Models\ProveedorModel;
use App\Models\CategoriaModel;
use App\Models\TiendaModel;
use CodeIgniter\Controller;

class Productos extends Controller
{
    protected ProductoModel $model;

    public function __construct()
    {
        $this->model = new ProductoModel();
    }

    public function index()
    {
        $allowed  = [25, 50, 100, 500, 1000];
        $perPage  = (int)($this->request->getGet('per_page') ?? 100);
        $perPage  = in_array($perPage, $allowed) ? $perPage : 100;

        $tiendas   = (new TiendaModel())->findAll();
        $productos = $this->model->getWithRelations($perPage);
        $pager     = $this->model->pager;

        $ids   = array_column($productos, 'id');
        $rawPT = $this->model->getAllProductoTiendas($ids);

        $productoTiendas = [];
        foreach ($rawPT as $pt) {
            $productoTiendas[$pt['producto_id']][$pt['tienda_id']] = $pt;
        }

        return view('productos/index', [
            'title'            => 'Productos',
            'productos'        => $productos,
            'tiendas'          => $tiendas,
            'producto_tiendas' => $productoTiendas,
            'pager'            => $pager,
            'perPage'          => $perPage,
            'marcas'           => (new MarcaModel())->orderBy('nombre')->findAll(),
            'categorias'       => (new CategoriaModel())->orderBy('nombre')->findAll(),
        ]);
    }

    public function create()
    {
        return view('productos/form', [
            'title' => 'Nuevo Producto',
            'producto' => null,
            'marcas' => (new MarcaModel())->orderBy('nombre')->findAll(),
            'proveedores' => (new ProveedorModel())->orderBy('nombre')->findAll(),
            'categorias' => (new CategoriaModel())->findAll(),
            'tiendas' => (new TiendaModel())->findAll(),
            'categorias_sel' => [],
            'tiendas_config' => [],
        ]);
    }

    public function store()
    {
        $data = $this->request->getPost(['nombre', 'marca_id', 'precio', 'precio_oferta', 'costo', 'stock_general', 'proveedor_id']);
        $data['precio_oferta'] = $data['precio_oferta'] ?: null;
        $data['marca_id'] = $data['marca_id'] ?: null;
        $data['proveedor_id'] = $data['proveedor_id'] ?: null;

        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        $id = $this->model->getInsertID();

        $categorias = $this->request->getPost('categorias') ?? [];
        $this->model->syncCategorias($id, $categorias);

        $tiendas = $this->request->getPost('tiendas') ?? [];
        $this->model->syncTiendas($id, $tiendas);

        return redirect()->to(site_url('productos'))->with('success', 'Producto creado correctamente.');
    }

    public function edit(int $id)
    {
        $producto = $this->model->find($id);
        if (!$producto) return redirect()->to(site_url('productos'))->with('error', 'Producto no encontrado.');

        $categoriasSel = array_column($this->model->getCategorias($id), 'id');
        $tiendasConfig = [];
        foreach ($this->model->getTiendas($id) as $t) {
            $tiendasConfig[$t['id']] = $t;
        }

        return view('productos/form', [
            'title' => 'Editar Producto',
            'producto' => $producto,
            'marcas' => (new MarcaModel())->orderBy('nombre')->findAll(),
            'proveedores' => (new ProveedorModel())->orderBy('nombre')->findAll(),
            'categorias' => (new CategoriaModel())->findAll(),
            'tiendas' => (new TiendaModel())->findAll(),
            'categorias_sel' => $categoriasSel,
            'tiendas_config' => $tiendasConfig,
        ]);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost(['nombre', 'marca_id', 'precio', 'precio_oferta', 'costo', 'stock_general', 'proveedor_id']);
        $data['precio_oferta'] = $data['precio_oferta'] ?: null;
        $data['marca_id'] = $data['marca_id'] ?: null;
        $data['proveedor_id'] = $data['proveedor_id'] ?: null;

        if (!$this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        $categorias = $this->request->getPost('categorias') ?? [];
        $this->model->syncCategorias($id, $categorias);

        $tiendas = $this->request->getPost('tiendas') ?? [];
        $this->model->syncTiendas($id, $tiendas);

        return redirect()->to(site_url('productos'))->with('success', 'Producto actualizado.');
    }

    public function bulkAction()
    {
        $accion = $this->request->getPost('accion');
        $ids    = $this->request->getPost('ids');

        if (empty($ids) || !is_array($ids)) {
            return redirect()->to(site_url('productos'))->with('error', 'No se seleccionaron productos.');
        }

        $ids = array_map('intval', $ids);

        switch ($accion) {
            case 'asignar_marca':
                $marcaId  = $this->request->getPost('marca_id');
                $marcaId  = $marcaId !== '' ? (int)$marcaId : null;
                $affected = $this->model->bulkUpdateMarca($ids, $marcaId);
                return redirect()->to(site_url('productos'))
                    ->with('success', "{$affected} producto(s) actualizados con la marca seleccionada.");

            case 'asignar_categoria':
                $categoriaId = (int)$this->request->getPost('categoria_id');
                if (!$categoriaId) {
                    return redirect()->to(site_url('productos'))->with('error', 'Debes seleccionar una categoría.');
                }
                $affected = $this->model->bulkAddCategoria($ids, $categoriaId);
                return redirect()->to(site_url('productos'))
                    ->with('success', "{$affected} producto(s) añadidos a la categoría.");

            case 'activar_tienda':
                $tiendaId = (int)$this->request->getPost('tienda_id');
                if (!$tiendaId) {
                    return redirect()->to(site_url('productos'))->with('error', 'Debes seleccionar una tienda.');
                }
                $affected = $this->model->bulkActivarEnTienda($ids, $tiendaId);
                $ya = count($ids) - $affected;
                $msg = "{$affected} producto(s) activados en la tienda.";
                if ($ya > 0) {
                    $msg .= " ({$ya} ya estaban activos, sin cambios.)";
                }
                return redirect()->to(site_url('productos'))->with('success', $msg);

            default:
                return redirect()->to(site_url('productos'))->with('error', 'Acción no reconocida.');
        }
    }

    public function delete(int $id)
    {
        $db = \Config\Database::connect();
        $db->table('producto_categoria')->where('producto_id', $id)->delete();
        $db->table('producto_tienda')->where('producto_id', $id)->delete();
        $this->model->delete($id);
        return redirect()->to(site_url('productos'))->with('success', 'Producto eliminado.');
    }
}
