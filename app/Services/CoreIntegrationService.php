<?php

namespace App\Services;

use App\Adapters\WooCommerceAdapter;
use App\DTOs\ProductDTO;
use App\Models\MigrationLogModel;
use App\Models\ProductoModel;

/**
 * Core Integration Service (CIS).
 *
 * Orquesta el flujo completo de sincronización en tres fases:
 *
 *   1. Catálogo interno: crea o actualiza el producto en `productos` y lo
 *      habilita en la tienda Jumpseller con su external_id.
 *
 *   2. WooCommerce: empuja el producto a la tienda destino via API.
 *
 *   3. Cross-reference: guarda el ID devuelto por WooCommerce en
 *      `producto_tienda` para la tienda WooCommerce, completando el cruce.
 *
 * Si ProductoModel y los tienda_ids no se inyectan (uso sin migración),
 * las fases 1 y 3 se omiten y solo se realiza el push a WooCommerce.
 */
class CoreIntegrationService
{
    private MigrationLogModel $logModel;
    private ?ProductoModel $productoModel;
    private int $jumpsellerTiendaId;
    private int $wooTiendaId;

    public function __construct(
        MigrationLogModel $logModel,
        ?ProductoModel $productoModel = null,
        int $jumpsellerTiendaId = 0,
        int $wooTiendaId = 0
    ) {
        $this->logModel           = $logModel;
        $this->productoModel      = $productoModel;
        $this->jumpsellerTiendaId = $jumpsellerTiendaId;
        $this->wooTiendaId        = $wooTiendaId;
    }

    /**
     * Procesa un batch de ProductDTOs en tres fases:
     *   Fase 1 — Sync catálogo interno (productos + producto_tienda Jumpseller)
     *   Fase 2 — Push a WooCommerce (simple en batch, variables individualmente)
     *   Fase 3 — Guarda external_id de WooCommerce en producto_tienda
     *
     * @param  ProductDTO[] $dtos
     * @return array{created: int, updated: int, skipped: int, errors: int}
     */
    public function syncToWooCommerce(array $dtos, WooCommerceAdapter $woo): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        $simpleProducts   = [];
        $variableProducts = [];
        // SKU → producto_id interno (construido en Fase 1)
        $skuToProductoId  = [];
        // SKU → bool: true si el producto fue creado nuevo en Fase 1
        $skuToIsNew       = [];
        // Caché local: nombre de marca normalizado → WooCommerce brand ID
        $brandIdCache     = [];
        // Caché: nombre de marca normalizado → ID de categoría local
        $localCatIdCache  = [];
        // Caché: nombre de marca normalizado → WooCommerce category ID
        $wooCatIdCache    = [];

        foreach ($dtos as $dto) {
            if (empty($dto->sku)) {
                $this->log('', $dto->name, 'skip', 'warning', 'SKU vacío — producto omitido.');
                $summary['skipped']++;
                continue;
            }

            // --- Fase 1: sync catálogo interno ---
            if ($this->productoModel && $this->jumpsellerTiendaId) {
                $upsertResult = $this->productoModel->upsertFromDto($dto, $this->jumpsellerTiendaId);
                if ($upsertResult['id']) {
                    $skuToProductoId[$dto->sku] = $upsertResult['id'];
                    $skuToIsNew[$dto->sku]       = $upsertResult['isNew'];
                }
            }

            // Resolver brand ID de WooCommerce y categoría mínima (= marca) en local y WooCommerce
            if ($dto->brand !== '') {
                $cacheKey = mb_strtoupper(trim($dto->brand));

                // Brand en WooCommerce
                if (!array_key_exists($cacheKey, $brandIdCache)) {
                    $brandIdCache[$cacheKey] = $woo->findOrCreateBrand($dto->brand);
                }
                if ($brandIdCache[$cacheKey] !== null) {
                    $dto->wooCommerceBrandId = $brandIdCache[$cacheKey];
                }

                // Categoría local equivalente a la marca (find-or-create)
                if (!array_key_exists($cacheKey, $localCatIdCache)) {
                    $catModel = model('App\Models\CategoriaModel');
                    $localCatIdCache[$cacheKey] = $catModel->findOrCreateByName($dto->brand);
                }
                $localCatId = $localCatIdCache[$cacheKey];

                // Vincular producto a categoría local (solo si ya tenemos el producto_id)
                if ($localCatId && $this->productoModel && isset($skuToProductoId[$dto->sku])) {
                    $this->productoModel->bulkAddCategoria([$skuToProductoId[$dto->sku]], $localCatId);
                }

                // Categoría en WooCommerce (find-or-create)
                if (!array_key_exists($cacheKey, $wooCatIdCache)) {
                    $wooCatIdCache[$cacheKey] = $woo->findOrCreateCategory($dto->brand);
                }
                if ($wooCatIdCache[$cacheKey] !== null) {
                    $dto->wooCategoryIds[] = $wooCatIdCache[$cacheKey];
                }
            }

            if ($dto->type === 'variable') {
                $variableProducts[] = $dto;
            } else {
                $simpleProducts[] = $dto;
            }
        }

        // --- Fase 2a: productos simples en batch ---
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

            // --- Fase 3a: guardar WooCommerce external_id para productos simples ---
            // Productos nuevos: se inserta el registro en producto_tienda (activa la tienda WooCommerce).
            // Productos existentes: solo se actualiza si el registro ya existía (no altera disponibilidad).
            if ($this->productoModel && $this->wooTiendaId && !empty($result['id_map'])) {
                foreach ($result['id_map'] as $sku => $wooId) {
                    $productoId = $skuToProductoId[$sku] ?? null;
                    if ($productoId) {
                        $isNew = $skuToIsNew[$sku] ?? false;
                        $this->productoModel->setExternalId($productoId, $this->wooTiendaId, (string)$wooId, $isNew);
                    }
                }
            }
        }

        // --- Fase 2b + 3b: productos variables individualmente ---
        foreach ($variableProducts as $dto) {
            $result = $this->syncVariableProduct($dto, $woo);

            if ($result['action'] === 'error') {
                $summary['errors']++;
                continue;
            }

            $summary[$result['action']]++;

            // Fase 3b: guardar WooCommerce external_id del producto variable
            // Misma lógica que 3a: insertar solo si es producto nuevo.
            if ($this->productoModel && $this->wooTiendaId && !empty($result['woo_id'])) {
                $productoId = $skuToProductoId[$dto->sku] ?? null;
                if ($productoId) {
                    $isNew = $skuToIsNew[$dto->sku] ?? false;
                    $this->productoModel->setExternalId(
                        $productoId,
                        $this->wooTiendaId,
                        (string)$result['woo_id'],
                        $isNew
                    );
                }
            }
        }

        return $summary;
    }

    /**
     * Crea o actualiza un producto variable (padre + variantes) en WooCommerce.
     * Devuelve el WooCommerce product ID para que el llamador pueda guardarlo.
     */
    private function syncVariableProduct(ProductDTO $dto, WooCommerceAdapter $woo): array
    {
        $existing  = $woo->findProductBySku($dto->sku);
        $wooParent = $dto->toWooCommerce();

        if ($existing) {
            $updated = $woo->updateProduct($existing['id'], $wooParent);
            if (!$updated) {
                $this->log($dto->sku, $dto->name, 'update', 'error', 'Fallo al actualizar producto variable.');
                return ['action' => 'error', 'woo_id' => 0];
            }
            $parentId = $existing['id'];
            $action   = 'updated';
        } else {
            $created = $woo->createProduct($wooParent);
            if (empty($created['id'])) {
                $this->log($dto->sku, $dto->name, 'create', 'error', 'Fallo al crear producto variable.');
                return ['action' => 'error', 'woo_id' => 0];
            }
            $parentId = $created['id'];
            $action   = 'created';
        }

        if (!empty($dto->variants) && $parentId) {
            $variations = array_map(fn($v) => $v->toWooCommerce(), $dto->variants);
            $woo->batchCreateVariations($parentId, $variations);
        }

        $this->log($dto->sku, $dto->name, $action, 'success', "Producto variable {$action} (woo_id={$parentId}).");
        return ['action' => $action, 'woo_id' => $parentId];
    }

    public function getCheckpoint(): ?array
    {
        return $this->logModel->getCheckpoint();
    }

    public function saveCheckpoint(array $state): void
    {
        $this->logModel->saveCheckpoint($state);
    }

    public function clearCheckpoint(): void
    {
        $this->logModel->clearCheckpoint();
    }

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
