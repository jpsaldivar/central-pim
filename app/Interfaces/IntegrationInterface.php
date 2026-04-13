<?php

namespace App\Interfaces;

use App\DTOs\ProductDTO;

/**
 * Common contract for all platform integration adapters.
 * Each adapter (Jumpseller, WooCommerce, etc.) must implement this interface.
 */
interface IntegrationInterface
{
    /**
     * Returns the total number of products available in the platform.
     */
    public function getProductCount(): int;

    /**
     * Fetches a page of products normalized as ProductDTO objects.
     *
     * @return ProductDTO[]
     */
    public function fetchProducts(int $page, int $limit): array;

    /**
     * Finds a product by its SKU. Returns raw platform data or null if not found.
     */
    public function findProductBySku(string $sku): ?array;

    /**
     * Batch upsert (create or update) products on the platform.
     * Returns a summary: ['created' => int, 'updated' => int, 'errors' => []]
     *
     * @param ProductDTO[] $products
     */
    public function batchUpsertProducts(array $products): array;
}
