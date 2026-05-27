<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;

/**
 * Scraper para Sony Store Chile (https://store.sony.cl).
 *
 * Plataforma: VTEX IO con React (cliente-side rendering).
 * El HTML del sitio llega sin productos — los gallery items se montan
 * vía JavaScript después de la carga inicial.
 *
 * Estrategia: usar la API REST de catálogo de VTEX directamente.
 *   Base: https://clsonyb2c.vtexcommercestable.com.br
 *   Endpoint: /api/catalog_system/pub/products/search/?fq=C:/{id}/&_from=N&_to=M
 *
 * El category ID se resuelve automáticamente consultando el árbol de
 * categorías de VTEX a partir de la URL configurada en Config\Scrapers.
 *
 * Paginación: hasta 50 productos por request (límite de la API).
 * El total se lee del header `resources: N-M/TOTAL`.
 */
class SonyAdapter extends BaseScraperAdapter
{
    private const VTEX_API_BASE = 'https://clsonyb2c.vtexcommercestable.com.br';
    private const STORE_BASE    = 'https://store.sony.cl';
    private const PAGE_SIZE     = 50;

    public function getPlataforma(): string
    {
        return 'sony_scraper';
    }

    /**
     * @param  string[]            $urls  URLs de categorías de store.sony.cl
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array
    {
        // Resolver URL → category ID usando el árbol de VTEX (1 request)
        $categoryMap = $this->buildCategoryMap();

        $productos = [];
        $seen      = []; // deduplicar por path de producto

        foreach ($urls as $url) {
            // Normalizar URL a la forma exacta que usa VTEX en el árbol
            $path     = '/' . trim(parse_url($url, PHP_URL_PATH), '/');
            $fullUrl  = self::STORE_BASE . $path;

            $categoryId = $categoryMap[$fullUrl] ?? null;
            if ($categoryId === null) {
                log_message('warning', "[SonyAdapter] No se encontró category ID para: {$url}. " .
                    "IDs disponibles: " . implode(', ', array_map(
                        fn($u, $id) => "{$id}={$u}", array_keys($categoryMap), $categoryMap
                    )));
                continue;
            }

            log_message('info', "[SonyAdapter] Scrapeando categoría ID {$categoryId} ({$url})");
            $this->scrapeCategory($categoryId, $productos, $seen);
        }

        log_message('info', "[SonyAdapter] Total productos obtenidos: " . count($productos));

        return $productos;
    }

    // -------------------------------------------------------------------------
    // Resolución de categorías
    // -------------------------------------------------------------------------

    /**
     * Descarga el árbol de categorías de VTEX y devuelve un mapa URL → ID.
     * Ej: ["https://store.sony.cl/camaras/lentes" => 8, ...]
     */
    private function buildCategoryMap(): array
    {
        $map = [];
        try {
            $response = $this->http->get(
                self::VTEX_API_BASE . '/api/catalog_system/pub/category/tree/5',
                ['headers' => ['Accept' => 'application/json']]
            );
            $tree = json_decode($response->getBody()->getContents(), true);
            if (is_array($tree)) {
                $this->flattenTree($tree, $map);
            }
        } catch (\Exception $e) {
            log_message('error', "[SonyAdapter] Error al obtener category tree: " . $e->getMessage());
        }
        return $map;
    }

    /** Recorre recursivamente el árbol y puebla $map[url] = id */
    private function flattenTree(array $nodes, array &$map): void
    {
        foreach ($nodes as $node) {
            if (!empty($node['url']) && !empty($node['id'])) {
                // La URL viene como "https://store.sony.cl/camaras/lentes"
                $map[rtrim($node['url'], '/')] = (int)$node['id'];
            }
            if (!empty($node['children'])) {
                $this->flattenTree($node['children'], $map);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Paginación sobre la API de catálogo
    // -------------------------------------------------------------------------

    /**
     * Itera todas las páginas de la API para una categoría y acumula productos.
     */
    private function scrapeCategory(int $categoryId, array &$productos, array &$seen): void
    {
        $from  = 0;
        $total = PHP_INT_MAX; // se actualiza con el primer response

        while ($from < $total) {
            $to     = $from + self::PAGE_SIZE - 1;
            $apiUrl = self::VTEX_API_BASE .
                      "/api/catalog_system/pub/products/search/?fq=C:/{$categoryId}/&_from={$from}&_to={$to}";

            try {
                $response = $this->http->get($apiUrl, [
                    'headers' => ['Accept' => 'application/json'],
                ]);

                // Actualizar total desde header "resources: 0-49/68"
                $resourcesHeader = $response->getHeader('resources')[0] ?? '';
                if (preg_match('/\/(\d+)$/', $resourcesHeader, $m)) {
                    $total = (int)$m[1];
                }

                $items = json_decode($response->getBody()->getContents(), true);
                if (empty($items)) {
                    break;
                }

                foreach ($items as $product) {
                    $this->parseProduct($product, $productos, $seen);
                }

                $from += self::PAGE_SIZE;

                if ($from < $total) {
                    $this->throttle();
                }

            } catch (\Exception $e) {
                log_message('error', "[SonyAdapter] Error en API categoría {$categoryId} (from={$from}): " . $e->getMessage());
                break;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Mapeo de producto VTEX → DTO
    // -------------------------------------------------------------------------

    private function parseProduct(array $product, array &$productos, array &$seen): void
    {
        $link = $product['link'] ?? '';
        // El link viene con el dominio de la API (vtexcommercestable.com.br)
        // → reemplazar por store.sony.cl
        $path = parse_url($link, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $path = '/' . ltrim($path, '/');
        if (isset($seen[$path])) {
            return;
        }
        $seen[$path] = true;

        $nombre = trim($product['productName'] ?? '');
        if ($nombre === '') {
            return;
        }

        // Primer item (SKU base del producto)
        $items  = $product['items'] ?? [];
        $item   = $items[0] ?? [];

        $ean    = ($item['ean'] ?? '') ?: null;
        $refIds = $item['referenceId'] ?? [];
        $sku    = $refIds[0]['Value'] ?? null;

        // Precios del primer seller
        $sellers = $item['sellers'] ?? [];
        $offer   = $sellers[0]['commertialOffer'] ?? [];

        $listPrice = isset($offer['ListPrice']) ? (int)round((float)$offer['ListPrice']) : null;
        $sellPrice = isset($offer['Price'])     ? (int)round((float)$offer['Price'])     : null;

        // Si ListPrice > Price → hay descuento
        if ($listPrice !== null && $sellPrice !== null && $listPrice > $sellPrice) {
            $precioNormal = $listPrice;
            $precioOferta = $sellPrice;
        } else {
            // Sin descuento: usar Price (o ListPrice como fallback)
            $precioNormal = $sellPrice ?? $listPrice;
            $precioOferta = null;
        }

        $disponible = (bool)($offer['IsAvailable'] ?? false);

        $dto              = new ScrapedProductDTO($nombre);
        $dto->sku         = $sku;
        $dto->ean         = $ean;
        $dto->externalRef = $sku; // SKU como referencia estable entre runs
        $dto->precioNormal = $precioNormal;
        $dto->precioOferta = $precioOferta;
        $dto->disponible   = $disponible;
        $dto->url          = self::STORE_BASE . $path;

        $productos[] = $dto;
    }
}
