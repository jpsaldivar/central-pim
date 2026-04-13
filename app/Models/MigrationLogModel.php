<?php

namespace App\Models;

use CodeIgniter\Model;

class MigrationLogModel extends Model
{
    protected $table      = 'migration_logs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'tipo',
        'sku',
        'nombre_producto',
        'accion',
        'estado',
        'mensaje',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = null;

    /**
     * Fetch paginated logs, newest first.
     */
    public function getRecent(int $limit = 100, int $offset = 0): array
    {
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->findAll();
    }

    /**
     * Summary stats for the last migration session (since last migration_start event).
     */
    public function getLastSessionSummary(): array
    {
        $lastStart = $this->where('accion', 'migration_start')
                          ->orderBy('created_at', 'DESC')
                          ->first();

        if (!$lastStart) {
            return [];
        }

        $since = $lastStart['created_at'];

        return $this->where('created_at >=', $since)
                    ->where('accion !=', 'migration_start')
                    ->where('sku !=', '')
                    ->findAll();
    }

    /**
     * Count log entries by estado since the last migration start.
     */
    public function getLastSessionStats(): array
    {
        $lastStart = $this->where('accion', 'migration_start')
                          ->orderBy('created_at', 'DESC')
                          ->first();

        if (!$lastStart) {
            return ['success' => 0, 'error' => 0, 'warning' => 0, 'info' => 0, 'started_at' => null];
        }

        $since = $lastStart['created_at'];

        $rows = $this->select('estado, COUNT(*) as total')
                     ->where('created_at >=', $since)
                     ->groupBy('estado')
                     ->findAll();

        $stats = ['success' => 0, 'error' => 0, 'warning' => 0, 'info' => 0, 'started_at' => $since];
        foreach ($rows as $row) {
            $stats[$row['estado']] = (int)$row['total'];
        }

        return $stats;
    }
}
