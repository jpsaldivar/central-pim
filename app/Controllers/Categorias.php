<?php
namespace App\Controllers;

use App\Models\CategoriaModel;
use CodeIgniter\Controller;

class Categorias extends Controller
{
    protected CategoriaModel $model;

    public function __construct()
    {
        $this->model = new CategoriaModel();
    }

    public function index()
    {
        return view('categorias/index', [
            'title' => 'Categorías',
            'categorias' => $this->model->findAll(),
        ]);
    }

    public function create()
    {
        return view('categorias/form', [
            'title' => 'Nueva Categoría',
            'categoria' => null,
            'padres' => $this->model->findAll(),
        ]);
    }

    public function store()
    {
        $data = [
            'nombre' => $this->request->getPost('nombre'),
            'descripcion' => $this->request->getPost('descripcion'),
            'parent_id' => $this->request->getPost('parent_id') ?: null,
        ];
        if (!$this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(site_url('categorias'))->with('success', 'Categoría creada.');
    }

    public function edit(int $id)
    {
        $categoria = $this->model->find($id);
        if (!$categoria) return redirect()->to(site_url('categorias'))->with('error', 'Categoría no encontrada.');
        return view('categorias/form', [
            'title' => 'Editar Categoría',
            'categoria' => $categoria,
            'padres' => $this->model->getFlatList($id),
        ]);
    }

    public function update(int $id)
    {
        $data = [
            'nombre' => $this->request->getPost('nombre'),
            'descripcion' => $this->request->getPost('descripcion'),
            'parent_id' => $this->request->getPost('parent_id') ?: null,
        ];
        if (!$this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }
        return redirect()->to(site_url('categorias'))->with('success', 'Categoría actualizada.');
    }

    public function delete(int $id)
    {
        // Reassign children to no parent
        $this->model->where('parent_id', $id)->set(['parent_id' => null])->update();
        $this->model->delete($id);
        return redirect()->to(site_url('categorias'))->with('success', 'Categoría eliminada.');
    }
}
