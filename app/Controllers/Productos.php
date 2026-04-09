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
        return view('productos/index', [
            'title' => 'Productos',
            'productos' => $this->model->getWithRelations(),
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

        return redirect()->to('/productos')->with('success', 'Producto creado correctamente.');
    }

    public function edit(int $id)
    {
        $producto = $this->model->find($id);
        if (!$producto) return redirect()->to('/productos')->with('error', 'Producto no encontrado.');

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

        return redirect()->to('/productos')->with('success', 'Producto actualizado.');
    }

    public function delete(int $id)
    {
        $db = \Config\Database::connect();
        $db->table('producto_categoria')->where('producto_id', $id)->delete();
        $db->table('producto_tienda')->where('producto_id', $id)->delete();
        $this->model->delete($id);
        return redirect()->to('/productos')->with('success', 'Producto eliminado.');
    }
}
