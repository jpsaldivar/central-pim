<?php
namespace App\Models;
use CodeIgniter\Model;

class MarcaModel extends Model
{
    protected $table = 'marcas';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'nombre' => 'required|max_length[100]',
    ];
    protected $validationMessages = [
        'nombre' => ['required' => 'El nombre es obligatorio.'],
    ];
}
