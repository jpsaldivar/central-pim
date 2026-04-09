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
}
