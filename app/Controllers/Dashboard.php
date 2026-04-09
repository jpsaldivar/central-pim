<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class Dashboard extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();
        $data = [
            'title' => 'Dashboard',
            'total_productos'  => $db->table('productos')->countAllResults(),
            'total_tiendas'    => $db->table('tiendas')->countAllResults(),
            'total_proveedores'=> $db->table('proveedores')->countAllResults(),
            'total_marcas'     => $db->table('marcas')->countAllResults(),
            'total_categorias' => $db->table('categorias')->countAllResults(),
        ];
        return view('dashboard/index', $data);
    }
}
