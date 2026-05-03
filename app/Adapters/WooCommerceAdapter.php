<?php

namespace App\Adapters;

use App\DTOs\ProductDTO;
use App\Interfaces\IntegrationInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Adapter for the WooCommerce REST API v3.
 * Responsible for loading products (ETL: Load phase).
 *
 * Auth: Basic Auth (consumer_key + consumer_secret)
 * Docs: https://woocommerce.github.io/woocommerce-rest-api-docs/
 */
class WooCommerceAdapter implements IntegrationInterface
{
    private Client $client;
    private Client $clientV2;  // Para endpoints de plugins que solo están en v2 (ej. brands)
    private const MAX_BATCH = 25;

    public function __construct(string $storeUrl, string $consumerKey, string $consumerSecret)
    {
        $base = rtrim($storeUrl, '/');
        $auth = [$consumerKey, $consumerSecret];
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $this->client = new Client([
            'base_uri' => $base . '/wp-json/wc/v3/',
            'auth'     => $auth,
            'headers'  => $headers,
            'timeout'  => 120,
            'verify'   => true,
        ]);

        $this->clientV2 = new Client([
            'base_uri' => $base . '/wp-json/wc/v2/',
            'auth'     => $auth,
            'headers'  => $headers,
            'timeout'  => 60,
            'verify'   => true,
        ]);
    }

    public function getProductCount(): int
    {
        try {
            $response = $this->client->get('products', ['query' => ['per_page' => 1]]);
            $total = $response->getHeader('X-WP-Total');
            return (int)($total[0] ?? 0);
        } catch (GuzzleException $e) {
            log_message('error', '[WooCommerceAdapter::getProductCount] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetches raw WooCommerce products (not normalized to ProductDTO).
     * Used for auditing, not for migration flow.
     *
     * @return array[]
     */
    public function fetchProducts(int $page, int $limit): array
    {
        try {
            $response = $this->client->get('products', [
                'query' => ['page' => $page, 'per_page' => $limit],
            ]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            log_message('error', '[WooCommerceAdapter::fetchProducts] ' . $e->getMessage());
            return [];
        }
    }

    public function findProductBySku(string $sku): ?array
    {
        try {
            $response = $this->client->get('products', [
                'query' => ['sku' => $sku, 'per_page' => 1],
            ]);
            $data = json_decode($response->getBody()->getContents(), true) ?? [];
            return !empty($data) ? $data[0] : null;
        } catch (GuzzleException $e) {
            log_message('error', '[WooCommerceAdapter::findProductBySku] sku=' . $sku . ' ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Batch upsert: checks each SKU, then creates or updates via the batch endpoint.
     * Only handles simple products — use createProduct/updateProduct for variable ones.
     *
     * @param ProductDTO[] $products
     * @return array{created: int, updated: int, errors: string[], id_map: array<string, int>}
     *         id_map: SKU → WooCommerce product ID for every successfully processed product.
     */
    public function batchUpsertProducts(array $products): array
    {
        $toCreate = [];
        $toUpdate = [];
        $result   = ['created' => 0, 'updated' => 0, 'errors' => [], 'id_map' => []];

        foreach ($products as $dto) {
            $existing = $this->findProductBySku($dto->sku);
            $payload  = $dto->toWooCommerce();

            if ($existing) {
                $payload['id'] = $existing['id'];
                $toUpdate[]    = $payload;
            } else {
                $toCreate[] = $payload;
            }
        }

        foreach (array_chunk($toCreate, self::MAX_BATCH) as $chunk) {
            $response = $this->sendBatch($chunk, []);
            $result['created'] += count($response['create'] ?? []);
            $result['errors']   = array_merge($result['errors'], $response['_errors'] ?? []);
            foreach ($response['create'] ?? [] as $p) {
                if (!empty($p['sku']) && !empty($p['id'])) {
                    $result['id_map'][$p['sku']] = (int)$p['id'];
                }
            }
        }

        foreach (array_chunk($toUpdate, self::MAX_BATCH) as $chunk) {
            $response = $this->sendBatch([], $chunk);
            $result['updated'] += count($response['update'] ?? []);
            $result['errors']   = array_merge($result['errors'], $response['_errors'] ?? []);
            foreach ($response['update'] ?? [] as $p) {
                if (!empty($p['sku']) && !empty($p['id'])) {
                    $result['id_map'][$p['sku']] = (int)$p['id'];
                }
            }
        }

        return $result;
    }

    /**
     * Creates a single product. Returns the created product data or null on failure.
     */
    public function createProduct(array $wooPayload): ?array
    {
        try {
            $response = $this->client->post('products', ['json' => $wooPayload]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            log_message('error', '[WooCommerceAdapter::createProduct] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates a single product by ID. Returns the updated product data or null on failure.
     */
    public function updateProduct(int $id, array $wooPayload): ?array
    {
        try {
            $response = $this->client->put("products/{$id}", ['json' => $wooPayload]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            log_message('error', "[WooCommerceAdapter::updateProduct] id={$id} " . $e->getMessage());
            return null;
        }
    }

    /**
     * Batch creates variations for a variable product.
     * Uses the /products/{id}/variations/batch endpoint.
     *
     * @return array{create: array[]}
     */
    public function batchCreateVariations(int $productId, array $variations): array
    {
        try {
            $response = $this->client->post("products/{$productId}/variations/batch", [
                'json' => ['create' => $variations],
            ]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            log_message('error', "[WooCommerceAdapter::batchCreateVariations] productId={$productId} " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca una marca por nombre exacto en WooCommerce; si no existe, la crea.
     * Devuelve el ID de WooCommerce o null en caso de error.
     */
    /**
     * Busca una marca por nombre en WooCommerce (API v2 del plugin de brands);
     * si no existe, la crea. Devuelve el ID de WooCommerce o null en caso de error.
     */
    /**
     * Busca una marca por nombre en WooCommerce (API v2 del plugin de brands);
     * si no existe, la crea. Devuelve el ID de WooCommerce o null en caso de error.
     */
    /**
     * Busca una marca por nombre en WooCommerce (API v2 del plugin de brands);
     * si no existe, la crea. Devuelve el ID de WooCommerce o null en caso de error.
     * El nombre debe venir ya normalizado (mayúsculas, sin espacios extra).
     */
    public function findOrCreateBrand(string $nombre): ?int
    {
        $nombreNorm = mb_strtoupper(preg_replace('/\s+/', ' ', trim($nombre)));
        if ($nombreNorm === '') {
            return null;
        }

        try {
            $response = $this->clientV2->get('products/brands', [
                'query' => ['search' => $nombreNorm, 'per_page' => 100],
            ]);
            $brands = json_decode($response->getBody()->getContents(), true) ?? [];

            foreach ($brands as $brand) {
                $brandNorm = mb_strtoupper(preg_replace('/\s+/', ' ', trim($brand['name'] ?? '')));
                if ($brandNorm === $nombreNorm) {
                    return (int)$brand['id'];
                }
            }

            // No existe — crear con nombre normalizado
            $response = $this->clientV2->post('products/brands', [
                'json' => ['name' => $nombreNorm],
            ]);
            $created = json_decode($response->getBody()->getContents(), true);
            return !empty($created['id']) ? (int)$created['id'] : null;
        } catch (GuzzleException $e) {
            log_message('error', '[WooCommerceAdapter::findOrCreateBrand] ' . $nombreNorm . ' ' . $e->getMessage());
            return null;
        }
    }

    private function sendBatch(array $toCreate, array $toUpdate): array
    {
        try {
            $payload = [];
            if (!empty($toCreate)) {
                $payload['create'] = $toCreate;
            }
            if (!empty($toUpdate)) {
                $payload['update'] = $toUpdate;
            }

            $response = $this->client->post('products/batch', ['json' => $payload]);
            $data = json_decode($response->getBody()->getContents(), true) ?? [];
            $data['_errors'] = [];
            return $data;
        } catch (GuzzleException $e) {
            log_message('error', '[WooCommerceAdapter::sendBatch] ' . $e->getMessage());
            return ['create' => [], 'update' => [], '_errors' => [$e->getMessage()]];
        }
    }
}
