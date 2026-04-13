<?php

namespace App\Services;

use App\Adapters\JumpsellerAdapter;
use App\Adapters\WooCommerceAdapter;

/**
 * ETL Orchestrator for Jumpseller → WooCommerce migration.
 *
 * Follows Extract → Transform → Load:
 *   - Extract: paginate through Jumpseller products
 *   - Transform: ProductDTO normalizes the data (handled by the DTO layer)
 *   - Load:  CoreIntegrationService pushes to WooCommerce with business rules applied
 *
 * Designed to be called manually from the Migraciones controller.
 * For large catalogs, this should be moved to a background job/queue.
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
     * Run the full Jumpseller → WooCommerce migration.
     *
     * @return array{
     *   total_jumpseller: int,
     *   pages_processed: int,
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   errors: int,
     *   duration_seconds: float
     * }
     */
    public function run(): array
    {
        $startTime = microtime(true);

        // Prevent PHP timeout for large catalogs
        set_time_limit(0);

        $totalProducts = $this->jumpseller->getProductCount();
        $totalPages    = (int)ceil($totalProducts / self::PAGE_SIZE);

        $this->cis->log('', 'SISTEMA', 'migration_start', 'info',
            "Iniciando migración. Total productos Jumpseller: {$totalProducts}, páginas: {$totalPages}."
        );

        $summary = [
            'total_jumpseller' => $totalProducts,
            'pages_processed'  => 0,
            'created'          => 0,
            'updated'          => 0,
            'skipped'          => 0,
            'errors'           => 0,
        ];

        if ($totalProducts === 0) {
            $this->cis->log('', 'SISTEMA', 'migration_end', 'warning',
                'No se encontraron productos en Jumpseller.'
            );
            $summary['duration_seconds'] = round(microtime(true) - $startTime, 2);
            return $summary;
        }

        for ($page = 1; $page <= $totalPages; $page++) {
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
            $summary['pages_processed']++;

            $this->cis->log('', 'SISTEMA', 'page_processed', 'info',
                "Página {$page}/{$totalPages} procesada. "
                . "Creados: {$pageResult['created']}, Actualizados: {$pageResult['updated']}, "
                . "Omitidos: {$pageResult['skipped']}, Errores: {$pageResult['errors']}."
            );
        }

        $duration = round(microtime(true) - $startTime, 2);
        $summary['duration_seconds'] = $duration;

        $this->cis->log('', 'SISTEMA', 'migration_end', 'success',
            "Migración completada en {$duration}s. "
            . "Creados: {$summary['created']}, Actualizados: {$summary['updated']}, "
            . "Omitidos: {$summary['skipped']}, Errores: {$summary['errors']}."
        );

        return $summary;
    }
}
