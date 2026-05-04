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
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * Fetch paginated logs, newest first.
     */
    public function getRecent(int $limit = 100, int $offset = 0): array
    {
        return $this->orderBy('id', 'DESC')
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
     * Returns the active checkpoint state, or null if there is none.
     * A checkpoint is active when the last checkpoint record is not cleared.
     */
    public function getCheckpoint(): ?array
    {
        $row = $this->whereIn('accion', ['checkpoint', 'checkpoint_cleared'])
                    ->orderBy('id', 'DESC')
                    ->first();

        if (!$row || $row['accion'] === 'checkpoint_cleared') {
            return null;
        }

        $state = json_decode($row['mensaje'], true);
        if (is_array($state)) {
            $state['last_update'] = $row['created_at'];
        }

        return $state;
    }

    /**
     * Persist a checkpoint. Called after every successfully processed page.
     */
    public function saveCheckpoint(array $state): void
    {
        $this->insert([
            'tipo'            => 'jumpseller_to_woo',
            'sku'             => '',
            'nombre_producto' => 'SISTEMA',
            'accion'          => 'checkpoint',
            'estado'          => 'info',
            'mensaje'         => json_encode($state),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark checkpoint as cleared so the next getCheckpoint() returns null.
     */
    public function clearCheckpoint(): void
    {
        $this->insert([
            'tipo'            => 'jumpseller_to_woo',
            'sku'             => '',
            'nombre_producto' => 'SISTEMA',
            'accion'          => 'checkpoint_cleared',
            'estado'          => 'info',
            'mensaje'         => 'Migración completada o reiniciada.',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Checkpoint helpers for inventory sync (accion prefix: inv_)

    public function getInventarioCheckpoint(): ?array
    {
        $row = $this->whereIn('accion', ['inv_checkpoint', 'inv_checkpoint_cleared'])
                    ->orderBy('id', 'DESC')
                    ->first();

        if (!$row || $row['accion'] === 'inv_checkpoint_cleared') {
            return null;
        }

        $state = json_decode($row['mensaje'], true);
        if (is_array($state)) {
            $state['last_update'] = $row['created_at'];
        }

        return $state;
    }

    public function saveInventarioCheckpoint(array $state): void
    {
        $this->insert([
            'tipo'            => 'inventario_sync',
            'sku'             => '',
            'nombre_producto' => 'SISTEMA',
            'accion'          => 'inv_checkpoint',
            'estado'          => 'info',
            'mensaje'         => json_encode($state),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function clearInventarioCheckpoint(): void
    {
        $this->insert([
            'tipo'            => 'inventario_sync',
            'sku'             => '',
            'nombre_producto' => 'SISTEMA',
            'accion'          => 'inv_checkpoint_cleared',
            'estado'          => 'info',
            'mensaje'         => 'Sincronización de inventario completada o reiniciada.',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------------------------

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
