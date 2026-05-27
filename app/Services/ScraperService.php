<?php

namespace App\Services;

use App\Adapters\Scrapers\DroneStoreAdapter;
use App\Adapters\Scrapers\GoProAdapter;
use App\Adapters\Scrapers\ScraperInterface;
use App\Adapters\Scrapers\SonyAdapter;
use App\Models\ScraperProductoModel;
use App\Models\ScraperRunModel;
use App\Models\TiendaModel;
use Config\Scrapers as ScrapersConfig;

/**
 * Orquesta la ejecución completa de un scraping run:
 *
 *   1. Valida que la tienda existe y tiene configuración en Config\Scrapers.
 *   2. Resuelve el adapter correcto según tiendas.plataforma.
 *   3. Ejecuta el scrape pasando las URLs de categorías del config.
 *   4. Persiste los resultados en scraper_productos.
 *   5. Auto-match por EAN/SKU con el catálogo interno.
 *   6. Calcula totales (encontrados, nuevos, cambios de precio).
 *   7. Marca el run como completado o fallido.
 *
 * Devuelve el run_id para que el controller pueda redirigir al detalle.
 */
class ScraperService
{
    private ScraperRunModel     $runModel;
    private ScraperProductoModel $productoModel;
    private TiendaModel          $tiendaModel;
    private ScrapersConfig       $config;

    public function __construct()
    {
        $this->runModel      = model(ScraperRunModel::class);
        $this->productoModel = model(ScraperProductoModel::class);
        $this->tiendaModel   = model(TiendaModel::class);
        $this->config        = config('Scrapers');
    }

    /**
     * Ejecuta el scraping de una tienda y persiste los resultados.
     *
     * @throws \RuntimeException si la tienda no existe, no tiene plataforma
     *                           o no hay adapter para esa plataforma.
     */
    public function ejecutarRun(int $tiendaId): int
    {
        // --- Validaciones previas ---
        $tienda = $this->tiendaModel->find($tiendaId);
        if (!$tienda) {
            throw new \RuntimeException("Tienda #{$tiendaId} no encontrada.");
        }

        $plataforma = $tienda['plataforma'] ?? '';
        if (!isset($this->config->tiendas[$plataforma])) {
            throw new \RuntimeException(
                "La plataforma '{$plataforma}' no está configurada en Config\\Scrapers."
            );
        }

        // Evitar runs concurrentes para la misma tienda
        $enProgreso = $this->runModel
            ->where('tienda_id', $tiendaId)
            ->where('estado', 'en_progreso')
            ->first();

        if ($enProgreso) {
            throw new \RuntimeException(
                "Ya hay un run en progreso para esta tienda (run #{$enProgreso['id']})."
            );
        }

        // --- Iniciar run ---
        $runId = $this->runModel->iniciarRun($tiendaId);

        try {
            // --- Obtener URLs desde config ---
            $categorias = $this->config->tiendas[$plataforma]['categorias'];
            $urls       = array_column($categorias, 'url');

            if (empty($urls)) {
                throw new \RuntimeException(
                    "No hay categorías configuradas para '{$plataforma}' en Config\\Scrapers."
                );
            }

            // --- Scraping ---
            $adapter = $this->resolverAdapter($plataforma);
            $dtos    = $adapter->scrape($urls);

            if (empty($dtos)) {
                throw new \RuntimeException(
                    "El scraper no retornó productos. Posible cambio de estructura HTML en el sitio."
                );
            }

            // --- Persistir ---
            $totalEncontrados = $this->productoModel->insertarLote($dtos, $runId, $tiendaId);

            // --- Auto-match con catálogo interno ---
            $this->productoModel->autoMatchPorEanOSku($runId);

            // --- Calcular totales ---
            $totales = $this->calcularTotales($runId, $tiendaId, $totalEncontrados);

            // --- Completar run ---
            $this->runModel->completarRun($runId, $totales);

            log_message('info', "[ScraperService] Run #{$runId} completado para tienda #{$tiendaId} — " .
                "{$totales['encontrados']} encontrados, {$totales['nuevos']} nuevos, {$totales['cambios']} cambios.");

        } catch (\Throwable $e) {
            $this->runModel->marcarError($runId, $e->getMessage());
            log_message('error', "[ScraperService] Run #{$runId} fallido: " . $e->getMessage());
            throw $e;
        }

        return $runId;
    }

    /**
     * Calcula los tres totales comparando el run actual con el último run
     * completado de la misma tienda.
     *
     * - encontrados: total de productos del run actual
     * - nuevos:      productos cuyo external_ref o ean no estaba en el run anterior
     * - cambios:     productos cuyo precio_normal difiere del run anterior
     *
     * @return array{encontrados: int, nuevos: int, cambios: int}
     */
    private function calcularTotales(int $runId, int $tiendaId, int $totalEncontrados): array
    {
        $prevRun = $this->runModel->getUltimoRun($tiendaId);

        // Sin run anterior: todos son nuevos, ningún cambio
        if (!$prevRun) {
            return ['encontrados' => $totalEncontrados, 'nuevos' => $totalEncontrados, 'cambios' => 0];
        }

        $prevRunId = (int)$prevRun['id'];
        $db        = \Config\Database::connect();

        // Nuevos: productos del run actual que no aparecen en el run anterior
        // Matching por external_ref (preferido) o por ean como fallback
        $nuevos = (int)$db->query("
            SELECT COUNT(*) AS total
            FROM scraper_productos sp
            WHERE sp.run_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM scraper_productos prev
                  WHERE prev.run_id = ?
                    AND (
                        (sp.external_ref IS NOT NULL AND sp.external_ref != '' AND prev.external_ref = sp.external_ref)
                        OR
                        (sp.ean IS NOT NULL AND sp.ean != '' AND prev.ean = sp.ean)
                    )
              )
        ", [$runId, $prevRunId])->getRow()->total;

        // Cambios: productos presentes en ambos runs con precio_normal distinto
        $cambios = (int)$db->query("
            SELECT COUNT(*) AS total
            FROM scraper_productos sp
            INNER JOIN scraper_productos prev
                ON prev.run_id = ?
                AND (
                    (sp.external_ref IS NOT NULL AND sp.external_ref != '' AND prev.external_ref = sp.external_ref)
                    OR
                    (sp.ean IS NOT NULL AND sp.ean != '' AND prev.ean = sp.ean)
                )
            WHERE sp.run_id = ?
              AND sp.precio_normal  IS NOT NULL
              AND prev.precio_normal IS NOT NULL
              AND sp.precio_normal  != prev.precio_normal
        ", [$prevRunId, $runId])->getRow()->total;

        return [
            'encontrados' => $totalEncontrados,
            'nuevos'      => $nuevos,
            'cambios'     => $cambios,
        ];
    }

    /**
     * Factory: devuelve el adapter correcto según el slug de plataforma.
     *
     * @throws \RuntimeException si la plataforma no tiene adapter registrado.
     */
    private function resolverAdapter(string $plataforma): ScraperInterface
    {
        return match ($plataforma) {
            'dronestore_scraper' => new DroneStoreAdapter(),
            'gopro_scraper'      => new GoProAdapter(),
            'sony_scraper'       => new SonyAdapter(),
            default              => throw new \RuntimeException(
                "No existe adapter para la plataforma '{$plataforma}'."
            ),
        };
    }
}
