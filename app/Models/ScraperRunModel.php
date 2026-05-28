<?php

namespace App\Models;

use CodeIgniter\Model;

class ScraperRunModel extends Model
{
    protected $table      = 'scraper_runs';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'tienda_id',
        'estado',
        'total_encontrados',
        'total_nuevos',
        'total_cambios',
        'mensaje_error',
        'iniciado_en',
        'finalizado_en',
    ];

    /**
     * Crea un run nuevo en estado 'en_progreso' y devuelve su ID.
     */
    public function iniciarRun(int $tiendaId): int
    {
        $this->insert([
            'tienda_id'   => $tiendaId,
            'estado'      => 'en_progreso',
            'iniciado_en' => date('Y-m-d H:i:s'),
        ]);

        return $this->getInsertID();
    }

    /**
     * Marca el run como completado y guarda los totales.
     *
     * @param array{encontrados: int, nuevos: int, cambios: int} $totales
     */
    public function completarRun(int $runId, array $totales): void
    {
        $this->update($runId, [
            'estado'            => 'completado',
            'total_encontrados' => (int)($totales['encontrados'] ?? 0),
            'total_nuevos'      => (int)($totales['nuevos']      ?? 0),
            'total_cambios'     => (int)($totales['cambios']     ?? 0),
            'finalizado_en'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Marca el run como fallido y guarda el mensaje de error.
     */
    public function marcarError(int $runId, string $mensaje): void
    {
        $this->update($runId, [
            'estado'        => 'error',
            'mensaje_error' => mb_substr($mensaje, 0, 65535),
            'finalizado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Devuelve el run más reciente (de cualquier estado) para una tienda.
     * Útil para comparar precios con el run anterior.
     */
    public function getUltimoRun(int $tiendaId, string $estado = 'completado'): ?array
    {
        return $this->where('tienda_id', $tiendaId)
            ->where('estado', $estado)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Devuelve todos los runs de una tienda ordenados del más reciente al más antiguo,
     * incluyendo el nombre de la tienda.
     */
    public function getRunsDeTienda(int $tiendaId): array
    {
        return $this->where('tienda_id', $tiendaId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Devuelve el estado resumido de todas las tiendas scraper:
     * último run, estado y totales. Ordenado por nombre de tienda.
     */
    public function getResumenPorTienda(): array
    {
        $db = \Config\Database::connect();

        return $db->query("
            SELECT
                t.id            AS tienda_id,
                t.nombre        AS tienda_nombre,
                t.plataforma,
                sr.id           AS run_id,
                sr.estado,
                sr.total_encontrados,
                sr.total_nuevos,
                sr.total_cambios,
                sr.iniciado_en,
                sr.finalizado_en,
                sr.mensaje_error
            FROM tiendas t
            LEFT JOIN scraper_runs sr ON sr.id = (
                SELECT id FROM scraper_runs
                WHERE tienda_id = t.id
                ORDER BY id DESC
                LIMIT 1
            )
            WHERE (t.plataforma LIKE '%\_scraper' OR t.plataforma LIKE 'google\_sheets\_%')
            ORDER BY t.nombre ASC
        ")->getResultArray();
    }
}
