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

    /**
     * Normaliza el nombre de la marca y devuelve el ID existente o crea uno nuevo.
     * Normalización: trim, colapso de espacios múltiples, mayúsculas.
     * Ejemplos: "CANON", "Canon", "  canon  ", "CANON  S.A." → "CANON S.A."
     */
    public function findOrCreateByName(string $nombre): ?int
    {
        $normalizado = mb_strtoupper(preg_replace('/\s+/', ' ', trim($nombre)));
        if ($normalizado === '') {
            return null;
        }

        $existing = $this->where('nombre', $normalizado)->first();
        if ($existing) {
            return (int)$existing['id'];
        }

        $this->skipValidation(false)->insert(['nombre' => $normalizado]);
        $id = (int)$this->getInsertID();
        return $id > 0 ? $id : null;
    }
}
