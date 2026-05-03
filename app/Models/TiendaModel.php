<?php
namespace App\Models;
use CodeIgniter\Model;

class TiendaModel extends Model
{
    protected $table = 'tiendas';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre', 'plataforma', 'url_api', 'token_auth'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'nombre'      => 'required|max_length[100]',
        'plataforma'  => 'required|max_length[50]',
        'url_api'     => 'required|max_length[255]',
        'token_auth'  => 'required',
    ];
}
