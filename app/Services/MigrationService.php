<?php

namespace App\Services;

use App\Adapters\JumpsellerAdapter;
use App\Adapters\WooCommerceAdapter;

/**
 * ETL Orchestrator for Jumpseller → WooCommerce migration.
 *
 * Checkpoint strategy: after every processed page, inserts a row with
 * accion = 'checkpoint' in migration_logs. This allows resuming from
 * phpMyAdmin by inserting a checkpoint row manually if needed.
 */
class MigrationService
{
    private const PAGE_SIZE = 50;

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
     */
    public function run(): array
    {
        $this->cis->clearCheckpoint();

        return $this->execute(1, [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => 0,
        ]);
    }

    /**
     * Resume from the last checkpoint stored in migration_logs.
     * Returns null if there is no active checkpoint.
     */
    public function resume(): ?array
    {
        $state = $this->cis->getCheckpoint();

        if (!$state) {
            return null;
        }

        $startPage          = $state['last_completed_page'] + 1;
        $accumulatedSummary = $state['summary'];

        $this->cis->log('', 'SISTEMA', 'migration_resume', 'info',
            "Reanudando desde página {$startPage}/{$state['total_pages']}. "
            . "Acumulado — Creados: {$accumulatedSummary['created']}, "
            . "Actualizados: {$accumulatedSummary['updated']}, "
            . "Errores: {$accumulatedSummary['errors']}."
        );

        return $this->execute($startPage, $accumulatedSummary);
    }

    /**
     * Returns the current checkpoint state, or null if no migration is paused.
     */
    public function getState(): ?array
    {
        return $this->cis->getCheckpoint();
    }

    /**
     * Clear the active checkpoint without running a migration.
     */
    public function clearState(): void
    {
        $this->cis->clearCheckpoint();
    }

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
            $this->cis->clearCheckpoint();
            $summary['total_jumpseller'] = 0;
            $summary['pages_processed']  = 0;
            $summary['duration_seconds'] = round(microtime(true) - $startTime, 2);
            return $summary;
        }

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

            // Persist checkpoint after every page so DB always has a valid resume point
            $this->cis->saveCheckpoint([
                'status'              => 'in_progress',
                'total_products'      => $totalProducts,
                'total_pages'         => $totalPages,
                'last_completed_page' => $page,
                'summary'             => $summary,
            ]);
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->cis->log('', 'SISTEMA', 'migration_end', 'success',
            "Migración completada en {$duration}s. "
            . "Creados: {$summary['created']}, Actualizados: {$summary['updated']}, "
            . "Omitidos: {$summary['skipped']}, Errores: {$summary['errors']}."
        );

        $this->cis->clearCheckpoint();

        $summary['total_jumpseller'] = $totalProducts;
        $summary['pages_processed']  = $pagesProcessed;
        $summary['duration_seconds'] = $duration;

        return $summary;
    }
}
