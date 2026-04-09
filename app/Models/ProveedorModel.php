<?php
namespace App\Models;
use CodeIgniter\Model;

class ProveedorModel extends Model
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre', 'tiempo_encargo', 'contacto'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'nombre' => 'required|max_length[100]',
        'tiempo_encargo' => 'required|integer|greater_than_equal_to[0]',
        'contacto' => 'permit_empty|max_length[100]',
    ];
}
