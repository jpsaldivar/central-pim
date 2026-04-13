<?php

namespace App\Adapters;

use App\DTOs\ProductDTO;
use App\Interfaces\IntegrationInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Adapter for the Jumpseller V1 REST API.
 * Responsible for extracting products (ETL: Extract phase).
 *
 * Auth: Basic Auth (login + authtoken)
 * Docs: https://jumpseller.com/support/api/
 */
class JumpsellerAdapter implements IntegrationInterface
{
    private Client $client;
    private const BASE_URI = 'https://api.jumpseller.com/v1/';

    public function __construct(string $login, string $authtoken)
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URI,
            'auth'     => [$login, $authtoken],
            'headers'  => ['Accept' => 'application/json'],
            'timeout'  => 30,
        ]);
    }

    public function getProductCount(): int
    {
        try {
            $response = $this->client->get('products/count.json');
            $data = json_decode($response->getBody()->getContents(), true);
            return (int)($data['count'] ?? 0);
        } catch (GuzzleException $e) {
            log_message('error', '[JumpsellerAdapter::getProductCount] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @return ProductDTO[]
     */
    public function fetchProducts(int $page, int $limit): array
    {
        try {
            $response = $this->client->get('products.json', [
                'query' => [
                    'limit'  => $limit,
                    'page'   => $page,
                    'status' => 'all',
                ],
            ]);

            $raw = json_decode($response->getBody()->getContents(), true) ?? [];
            $products = [];

            foreach ($raw as $item) {
                // Jumpseller wraps each product: {"product": {...}}
                $product = $item['product'] ?? $item;
                if (!empty($product)) {
                    $products[] = ProductDTO::fromJumpseller($product);
                }
            }

            return $products;
        } catch (GuzzleException $e) {
            log_message('error', "[JumpsellerAdapter::fetchProducts] page={$page} " . $e->getMessage());
            return [];
        }
    }

    public function findProductBySku(string $sku): ?array
    {
        try {
            $response = $this->client->get('products.json', [
                'query' => ['sku' => $sku, 'limit' => 1],
            ]);
            $data = json_decode($response->getBody()->getContents(), true) ?? [];
            return !empty($data) ? ($data[0]['product'] ?? $data[0]) : null;
        } catch (GuzzleException $e) {
            log_message('error', '[JumpsellerAdapter::findProductBySku] sku=' . $sku . ' ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Not used: Jumpseller is the source platform for migration, not the target.
     */
    public function batchUpsertProducts(array $products): array
    {
        return ['created' => 0, 'updated' => 0, 'errors' => []];
    }
}
