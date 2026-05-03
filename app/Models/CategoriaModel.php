<?php
namespace App\Models;
use CodeIgniter\Model;

class CategoriaModel extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nombre', 'descripcion', 'parent_id'];
    protected $useTimestamps = false;

    protected $validationRules = [
        'nombre' => 'required|max_length[100]',
    ];

    public function getTree(): array
    {
        $all = $this->findAll();
        return $this->buildTree($all);
    }

    private function buildTree(array $items, int $parentId = 0): array
    {
        $branch = [];
        foreach ($items as $item) {
            $itemParent = $item['parent_id'] ?? 0;
            if ((int)$itemParent === $parentId) {
                $children = $this->buildTree($items, (int)$item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $branch[] = $item;
            }
        }
        return $branch;
    }

    public function getFlatList(int $excludeId = 0): array
    {
        $all = $this->where('id !=', $excludeId)->findAll();
        return $all;
    }

    /**
     * Normaliza el nombre y devuelve el ID existente o crea una categoría raíz nueva.
     * Misma normalización que MarcaModel: trim, colapso de espacios, mayúsculas.
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

        $this->insert(['nombre' => $normalizado, 'descripcion' => '', 'parent_id' => null]);
        $id = (int)$this->getInsertID();
        return $id > 0 ? $id : null;
    }
}
