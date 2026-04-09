<?php
namespace App\Controllers;

use App\Models\MarcaModel;
use CodeIgniter\Controller;

class Marcas extends Controller
{
    protected MarcaModel $model;

    public function __construct()
    {
        $this->model = new MarcaModel();
    }

    public function index()
    {
        return view('marcas/index', [
            'title' => 'Marcas',
            'marcas' => $this->model->orderBy('nombre')->findAll(),
        ]);
    }

    public function create()
    {
        return view('marcas/form', ['title' => 'Nueva Marca', 'marca' => null]);
    }

    public function store()
    {
        $data = ['nombre' => $this->request->getPost('nombre')];
        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(site_url('marcas'))->with('success', 'Marca creada correctamente.');
    }

    public function edit(int $id)
    {
        $marca = $this->model->find($id);
        if (!$marca) return redirect()->to(site_url('marcas'))->with('error', 'Marca no encontrada.');
        return view('marcas/form', ['title' => 'Editar Marca', 'marca' => $marca]);
    }

    public function update(int $id)
    {
        $data = ['nombre' => $this->request->getPost('nombre')];
        if (!$this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(site_url('marcas'))->with('success', 'Marca actualizada.');
    }

    public function delete(int $id)
    {
        $this->model->delete($id);
        return redirect()->to(site_url('marcas'))->with('success', 'Marca eliminada.');
    }
}
