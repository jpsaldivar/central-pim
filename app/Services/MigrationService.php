<?php

namespace App\Services;

use App\Adapters\JumpsellerAdapter;
use App\Adapters\WooCommerceAdapter;

/**
 * ETL Orchestrator for Jumpseller → WooCommerce migration.
 *
 * Supports resumable execution: persists progress in a JSON state file
 * so that if the hosting kills the process mid-run, the migration can
 * continue from the last successfully completed page.
 *
 * State file: writable/migration_state.json
 */
class MigrationService
{
    private const PAGE_SIZE   = 50;
    private const STATE_FILE  = WRITEPATH . 'migration_state.json';

    private JumpsellerAdapter $jumpseller;
    private WooCommerceAdapter $woocommerce;
    private CoreIntegrationService $cis;

    public function __construct(
        JumpsellerAdapter $jumpseller,
        WooCommerceAdapter $woocommerce,
        CoreIntegrationService $cis
    ) {
        $this->jumpseller  = $jumpseller;
        $this->woocommerce = $woocommerce;
        $this->cis         = $cis;
    }

    /**
     * Start a fresh migration from page 1.
     * Overwrites any existing state file.
     */
    public function run(): array
    {
        return $this->execute(1, [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        ]);
    }

    /**
     * Resume from the last completed page stored in the state file.
     * Returns null if there is nothing to resume.
     */
    public function resume(): ?array
    {
        $state = $this->readState();

        if (!$state || $state['status'] !== 'in_progress') {
            return null;
        }

        $startPage       = $state['last_completed_page'] + 1;
        $accumulatedSummary = $state['summary'];

        $this->cis->log('', 'SISTEMA', 'migration_resume', 'info',
            "Reanudando migración desde página {$startPage}. "
            . "Progreso previo — Creados: {$accumulatedSummary['created']}, "
            . "Actualizados: {$accumulatedSummary['updated']}, "
            . "Errores: {$accumulatedSummary['errors']}."
        );

        return $this->execute($startPage, $accumulatedSummary);
    }

    /**
     * Delete the state file, allowing a clean start.
     */
    public function clearState(): void
    {
        if (file_exists(self::STATE_FILE)) {
            unlink(self::STATE_FILE);
        }
    }

    /**
     * Return current migration state or null if no state file exists.
     */
    public function getState(): ?array
    {
        return $this->readState();
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function execute(int $startPage, array $summary): array
    {
        $startTime = microtime(true);
        set_time_limit(0);

        $totalProducts = $this->jumpseller->getProductCount();
        $totalPages    = (int)ceil($totalProducts / self::PAGE_SIZE);

        if ($startPage === 1) {
            $this->cis->log('', 'SISTEMA', 'migration_start', 'info',
                "Iniciando migración. Total productos Jumpseller: {$totalProducts}, páginas: {$totalPages}."
            );
        }

        if ($totalProducts === 0) {
            $this->cis->log('', 'SISTEMA', 'migration_end', 'warning',
                'No se encontraron productos en Jumpseller.'
            );
            $this->clearState();
            $summary['total_jumpseller']  = 0;
            $summary['pages_processed']   = 0;
            $summary['duration_seconds']  = round(microtime(true) - $startTime, 2);
            return $summary;
        }

        // Write initial state so it exists even if the first page kills the process
        $this->writeState([
            'status'              => 'in_progress',
            'total_products'      => $totalProducts,
            'total_pages'         => $totalPages,
            'last_completed_page' => $startPage - 1,
            'started_at'          => date('Y-m-d H:i:s'),
            'summary'             => $summary,
        ]);

        $pagesProcessed = 0;

        for ($page = $startPage; $page <= $totalPages; $page++) {
            $dtos = $this->jumpseller->fetchProducts($page, self::PAGE_SIZE);

            if (empty($dtos)) {
                $this->cis->log('', 'SISTEMA', 'fetch_page', 'warning',
                    "Página {$page}: sin productos. Se detiene la paginación."
                );
                break;
            }

            $pageResult = $this->cis->syncToWooCommerce($dtos, $this->woocommerce);

            $summary['created'] += $pageResult['created'];
            $summary['updated'] += $pageResult['updated'];
            $summary['skipped'] += $pageResult['skipped'];
            $summary['errors']  += $pageResult['errors'];
            $pagesProcessed++;

            $this->cis->log('', 'SISTEMA', 'page_processed', 'info',
                "Página {$page}/{$totalPages} procesada. "
                . "Creados: {$pageResult['created']}, Actualizados: {$pageResult['updated']}, "
                . "Omitidos: {$pageResult['skipped']}, Errores: {$pageResult['errors']}."
            );

            // Persist progress after every page so resume always has a valid checkpoint
            $this->writeState([
                'status'              => 'in_progress',
                'total_products'      => $totalProducts,
                'total_pages'         => $totalPages,
                'last_completed_page' => $page,
                'started_at'          => date('Y-m-d H:i:s'),
                'summary'             => $summary,
            ]);
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->cis->log('', 'SISTEMA', 'migration_end', 'success',
            "Migración completada en {$duration}s. "
            . "Creados: {$summary['created']}, Actualizados: {$summary['updated']}, "
            . "Omitidos: {$summary['skipped']}, Errores: {$summary['errors']}."
        );

        // Clear state: migration finished successfully
        $this->clearState();

        $summary['total_jumpseller'] = $totalProducts;
        $summary['pages_processed']  = $pagesProcessed;
        $summary['duration_seconds'] = $duration;

        return $summary;
    }

    private function writeState(array $state): void
    {
        file_put_contents(self::STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function readState(): ?array
    {
        if (!file_exists(self::STATE_FILE)) {
            return null;
        }

        $data = json_decode(file_get_contents(self::STATE_FILE), true);
        return is_array($data) ? $data : null;
    }
}
