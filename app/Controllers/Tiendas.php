<?php
namespace App\Controllers;

use App\Models\TiendaModel;
use CodeIgniter\Controller;

class Tiendas extends Controller
{
    protected TiendaModel $model;

    public function __construct()
    {
        $this->model = new TiendaModel();
    }

    public function index()
    {
        return view('tiendas/index', [
            'title' => 'Tiendas',
            'tiendas' => $this->model->findAll(),
        ]);
    }

    public function create()
    {
        return view('tiendas/form', ['title' => 'Nueva Tienda', 'tienda' => null]);
    }

    public function store()
    {
        $data = $this->request->getPost(['nombre', 'url_api', 'token_auth']);
        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to('/tiendas')->with('success', 'Tienda creada.');
    }

    public function edit(int $id)
    {
        $tienda = $this->model->find($id);
        if (!$tienda) return redirect()->to('/tiendas')->with('error', 'Tienda no encontrada.');
        return view('tiendas/form', ['title' => 'Editar Tienda', 'tienda' => $tienda]);
    }

    public function update(int $id)
    {
        $data = $this->request->getPost(['nombre', 'url_api', 'token_auth']);
        if (!$this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to('/tiendas')->with('success', 'Tienda actualizada.');
    }

    public function delete(int $id)
    {
        $this->model->delete($id);
        return redirect()->to('/tiendas')->with('success', 'Tienda eliminada.');
    }
}
