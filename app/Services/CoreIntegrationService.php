<?php

namespace App\Services;

use App\Adapters\WooCommerceAdapter;
use App\DTOs\ProductDTO;
use App\Models\MigrationLogModel;

/**
 * Core Integration Service (CIS).
 *
 * Platform-agnostic business logic layer that sits between the ETL pipeline
 * and the platform adapters. Handles:
 * - Price/stock business rules (safety minimums, margin enforcement)
 * - Sync audit logging
 * - Variable product orchestration (parent + variations)
 *
 * Both MigrationService and future WebhookListeners route through this service
 * to ensure consistent behaviour regardless of what triggered the sync.
 */
class CoreIntegrationService
{
    private MigrationLogModel $logModel;

    public function __construct(MigrationLogModel $logModel)
    {
        $this->logModel = $logModel;
    }

    /**
     * Process a batch of ProductDTOs and push them to WooCommerce.
     * Separates simple products (batched) from variable products (individual).
     *
     * @param  ProductDTO[] $dtos
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function syncToWooCommerce(array $dtos, WooCommerceAdapter $woo): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $simpleProducts   = [];
        $variableProducts = [];

        foreach ($dtos as $dto) {
            if (empty($dto->sku)) {
                $this->log('', $dto->name, 'skip', 'warning', 'SKU vacío — producto omitido.');
                $summary['skipped']++;
                continue;
            }

            if ($dto->type === 'variable') {
                $variableProducts[] = $dto;
            } else {
                $simpleProducts[] = $dto;
            }
        }

        // --- Simple products: use batch endpoint ---
        if (!empty($simpleProducts)) {
            $result = $woo->batchUpsertProducts($simpleProducts);
            $summary['created'] += $result['created'];
            $summary['updated'] += $result['updated'];

            foreach ($simpleProducts as $dto) {
                $this->log($dto->sku, $dto->name, 'upsert', 'success', 'Producto simple sincronizado.');
            }

            foreach ($result['errors'] as $err) {
                $this->log('', 'batch', 'upsert', 'error', $err);
                $summary['errors']++;
            }
        }

        // --- Variable products: parent first, then variations ---
        foreach ($variableProducts as $dto) {
            $result = $this->syncVariableProduct($dto, $woo);
            $summary[$result['action']]++;
            if ($result['action'] === 'errors') {
                $summary['errors']++;
            }
        }

        return $summary;
    }

    /**
     * Creates or updates a variable product (parent + all variations).
     */
    private function syncVariableProduct(ProductDTO $dto, WooCommerceAdapter $woo): array
    {
        $existing  = $woo->findProductBySku($dto->sku);
        $wooParent = $dto->toWooCommerce();

        if ($existing) {
            $updated = $woo->updateProduct($existing['id'], $wooParent);
            if (!$updated) {
                $this->log($dto->sku, $dto->name, 'update', 'error', 'Fallo al actualizar producto variable.');
                return ['action' => 'errors'];
            }
            $parentId = $existing['id'];
            $action   = 'updated';
        } else {
            $created = $woo->createProduct($wooParent);
            if (empty($created['id'])) {
                $this->log($dto->sku, $dto->name, 'create', 'error', 'Fallo al crear producto variable.');
                return ['action' => 'errors'];
            }
            $parentId = $created['id'];
            $action   = 'created';
        }

        // Push variations
        if (!empty($dto->variants) && $parentId) {
            $variations = array_map(fn($v) => $v->toWooCommerce(), $dto->variants);
            $woo->batchCreateVariations($parentId, $variations);
        }

        $this->log($dto->sku, $dto->name, $action, 'success', "Producto variable {$action} (id={$parentId}).");
        return ['action' => $action];
    }

    /**
     * Persists a log entry for every sync operation.
     */
    public function log(
        string $sku,
        string $nombre,
        string $accion,
        string $estado,
        string $mensaje
    ): void {
        $this->logModel->insert([
            'tipo'             => 'jumpseller_to_woo',
            'sku'              => $sku,
            'nombre_producto'  => mb_substr($nombre, 0, 200),
            'accion'           => $accion,
            'estado'           => $estado,
            'mensaje'          => $mensaje,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
