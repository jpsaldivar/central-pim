<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper para Sony Store Chile (https://store.sony.cl).
 *
 * Plataforma: VTEX IO con SSR (Server-Side Rendering).
 * Con el parámetro ?page=N los productos se renderizan en servidor,
 * por lo que no se necesita Playwright/JS. Sin ese parámetro el sitio
 * usa scroll infinito y el HTML llega vacío de productos.
 *
 * Paginación: 10 productos por página. El total se lee del primer request.
 *
 * SKUs disponibles:
 *   - sku (MPN completo): .pasonyb2cqa-shelf-custom-0-x-labelSkuName  (ej: SEL70200GM/QSYX)
 *   - externalRef (base): .pasonyb2cqa-shelf-custom-0-x-labelAditionalDescription (ej: SEL70200GM)
 *
 * Nota: las clases vtex-* son estables (módulos core de VTEX).
 *       Las clases pasonyb2cqa-* son custom de Sony Chile y pueden cambiar.
 */
class SonyAdapter extends BaseScraperAdapter
{
    private const ITEMS_PER_PAGE = 10;

    public function getPlataforma(): string
    {
        return 'sony_scraper';
    }

    /**
     * @param  string[]            $urls  URLs de categorías configuradas en Config\Scrapers
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array
    {
        $productos   = [];
        $seen        = []; // evitar duplicados por href entre categorías
        $diagnostics = []; // recopila info para logs y mensajes de error

        foreach ($urls as $baseUrl) {
            $origin = $this->originFromUrl($baseUrl);
            $urlDiag = ['url' => $baseUrl, 'paginas_ok' => 0, 'paginas_fallidas' => 0, 'items' => 0];

            // Página 1: parsear productos y detectar total de páginas
            $crawler = $this->fetch($baseUrl . '?page=1');
            if ($crawler === null) {
                log_message('warning', "[SonyAdapter] No se pudo obtener {$baseUrl}?page=1");
                $urlDiag['paginas_fallidas']++;
                $diagnostics[] = $urlDiag;
                continue;
            }

            $urlDiag['paginas_ok']++;
            $totalPages = $this->detectTotalPages($crawler);
            $urlDiag['total_paginas_detectadas'] = $totalPages;

            // Diagnóstico: cuántos gallery items hay en la página 1
            $urlDiag['gallery_items_p1'] = $crawler->filter('.vtex-search-result-3-x-galleryItem')->count();

            $antesP1 = count($productos);
            $this->parsePage($crawler, $origin, $productos, $seen);
            $urlDiag['items'] += count($productos) - $antesP1;

            // Páginas siguientes
            for ($page = 2; $page <= $totalPages; $page++) {
                $this->throttle();
                $pageCrawler = $this->fetch($baseUrl . '?page=' . $page);
                if ($pageCrawler === null) {
                    log_message('warning', "[SonyAdapter] No se pudo obtener página {$page} de {$baseUrl}");
                    $urlDiag['paginas_fallidas']++;
                    continue;
                }
                $urlDiag['paginas_ok']++;
                $antes = count($productos);
                $this->parsePage($pageCrawler, $origin, $productos, $seen);
                $urlDiag['items'] += count($productos) - $antes;
            }

            $diagnostics[] = $urlDiag;
        }

        log_message('info', '[SonyAdapter] Diagnóstico: ' . json_encode($diagnostics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $productos;
    }

    /**
     * Lee el selector de cantidad para calcular el total de páginas.
     * El selector puede aparecer varias veces en el DOM (facetas);
     * tomamos el primer valor > 10 que encontremos.
     */
    private function detectTotalPages(Crawler $crawler): int
    {
        $total = 1;

        $crawler->filter('.pasonyb2cqa-category-custom-0-x-facetItem__quantity')->each(
            function (Crawler $el) use (&$total) {
                if (preg_match('/(\d+)/', $el->text(), $m) && (int)$m[1] > self::ITEMS_PER_PAGE) {
                    $pages = (int)ceil((int)$m[1] / self::ITEMS_PER_PAGE);
                    if ($pages > $total) {
                        $total = $pages;
                    }
                }
            }
        );

        return $total;
    }

    /**
     * Extrae los productos de una página ya descargada y los acumula en $productos.
     */
    private function parsePage(Crawler $crawler, string $origin, array &$productos, array &$seen): void
    {
        $crawler->filter('.vtex-search-result-3-x-galleryItem')->each(
            function (Crawler $item) use ($origin, &$productos, &$seen) {
                // --- URL ---
                $href = $this->nodeAttr($item, 'a.vtex-product-summary-2-x-clearLink', 'href');
                if (!$href || isset($seen[$href])) {
                    return;
                }
                $seen[$href] = true;

                // href viene como path relativo ("/sel70200gm/p"), construir URL absoluta
                $url = $origin . '/' . ltrim($href, '/');

                // --- Nombre ---
                $nombre = $this->nodeText($item, '.vtex-product-summary-2-x-productBrand');
                if ($nombre === null || $nombre === '') {
                    return;
                }

                // --- SKUs ---
                // MPN completo (ej: SEL70200GM/QSYX) — lo guardamos como sku
                $skuMpn  = $this->nodeText($item, '.pasonyb2cqa-shelf-custom-0-x-labelSkuName');
                // SKU base sin sufijo de región (ej: SEL70200GM) — lo usamos como externalRef estable
                $skuBase = $this->nodeText($item, '.pasonyb2cqa-shelf-custom-0-x-labelAditionalDescription');

                // --- Precios ---
                $listPriceEl = $item->filter('.vtex-product-price-1-x-listPriceValue');
                $sellPriceEl = $item->filter('.vtex-product-price-1-x-sellingPriceValue');

                $tieneDescuento = $listPriceEl->count() > 0;

                if ($tieneDescuento && $sellPriceEl->count() > 0) {
                    $precioNormal = $this->parsePrice($listPriceEl->first()->text());
                    $precioOferta = $this->parsePrice($sellPriceEl->first()->text());
                } elseif ($sellPriceEl->count() > 0) {
                    $precioNormal = $this->parsePrice($sellPriceEl->first()->text());
                    $precioOferta = null;
                } else {
                    $precioNormal = null;
                    $precioOferta = null;
                }

                // Disponible: si aparece el precio de venta, el producto está activo
                $disponible = $sellPriceEl->count() > 0;

                $dto              = new ScrapedProductDTO($nombre);
                $dto->sku         = $skuMpn ?? $skuBase;
                $dto->externalRef = $skuBase;
                $dto->precioNormal = $precioNormal;
                $dto->precioOferta = $precioOferta;
                $dto->disponible  = $disponible;
                $dto->url         = $url;

                $productos[] = $dto;
            }
        );
    }

    /**
     * Extrae el origin (scheme + host) de una URL.
     * Ej: "https://store.sony.cl/camaras/lentes" → "https://store.sony.cl"
     */
    private function originFromUrl(string $url): string
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    }
}
