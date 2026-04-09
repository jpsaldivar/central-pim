<?php
namespace App\Controllers;

use App\Models\ProveedorModel;
use CodeIgniter\Controller;

class Proveedores extends Controller
{
    protected ProveedorModel $model;

    public function __construct()
    {
        $this->model = new ProveedorModel();
    }

    public function index()
    {
        return view('proveedores/index', [
            'title' => 'Proveedores',
            'proveedores' => $this->model->orderBy('nombre')->findAll(),
        ]);
    }

    public function create()
    {
        return view('proveedores/form', ['title' => 'Nuevo Proveedor', 'proveedor' => null]);
    }

    public function store()
    {
        $data = $this->request->getPost(['nombre', 'tiempo_encargo', 'contacto']);
        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(site_url('proveedores'))->with('success', 'Proveedor creado correctamente.');
    }

    public function edit(int $id)
    {
        $proveedor = $this->model->find($id);
        if (!$proveedor) return redirect()->to(site_url('proveedores'))->with('error', 'Proveedor no encontrado.');
        return view('proveedores/form', ['title' => 'Editar Proveedor', 'proveedor' => $proveedor]);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost(['nombre', 'tiempo_encargo', 'contacto']);
        if (!$this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(site_url('proveedores'))->with('success', 'Proveedor actualizado.');
    }

    public function delete(int $id)
    {
        $this->model->delete($id);
        return redirect()->to(site_url('proveedores'))->with('success', 'Proveedor eliminado.');
    }
}
