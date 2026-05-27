<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper para GoPro Chile (https://www.gopro.cl).
 *
 * Plataforma: WordPress + WooCommerce con tema personalizado gopro-2022.
 * Las páginas de categoría usan un layout custom (.row.item-camera),
 * no el grid estándar de WooCommerce.
 *
 * Flujo en 2 pasos:
 *   Paso 1: GET categoría → extrae URLs de productos
 *   Paso 2: GET cada página de producto → nombre completo, SKU, disponibilidad y precios
 *
 * El nombre real y el SKU solo están disponibles en la página individual.
 */
class GoProAdapter extends BaseScraperAdapter
{
    public function getPlataforma(): string
    {
        return 'gopro_scraper';
    }

    /**
     * @param  string[]            $urls  URLs de categorías configuradas en Config\Scrapers
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array
    {
        $productos = [];
        $seen      = []; // evitar duplicados por slug entre categorías

        foreach ($urls as $categoryUrl) {
            $crawler = $this->fetch($categoryUrl);
            if ($crawler === null) {
                log_message('warning', "[GoProAdapter] No se pudo obtener {$categoryUrl}");
                continue;
            }

            // --- Paso 1: recopilar URLs de productos del listado ---
            $productUrls = [];
            $crawler->filter('.row.item-camera')->each(
                function (Crawler $row) use (&$productUrls) {
                    // Tomar el primer botón "COMPRAR" (no el de comparar)
                    $link = $row->filter('a.button-gp[href*="producto"]');
                    if ($link->count() === 0) {
                        return;
                    }
                    $href = $link->first()->attr('href');
                    if ($href) {
                        $productUrls[] = $href;
                    }
                }
            );

            // --- Paso 2: visitar cada página de producto ---
            foreach ($productUrls as $productUrl) {
                $slug = $this->slugFromUrl($productUrl);
                if (isset($seen[$slug])) {
                    continue;
                }
                $seen[$slug] = true;

                $this->throttle();
                $dto = $this->scrapeProductPage($productUrl, $slug);
                if ($dto !== null) {
                    $productos[] = $dto;
                }
            }
        }

        return $productos;
    }

    /**
     * Scrapea la página individual de un producto y devuelve un DTO,
     * o null si no se puede obtener el nombre (página inválida).
     */
    private function scrapeProductPage(string $url, string $slug): ?ScrapedProductDTO
    {
        $crawler = $this->fetch($url);
        if ($crawler === null) {
            return null;
        }

        $nombre = $this->nodeText($crawler, 'h1.product_title');
        if ($nombre === null || $nombre === '') {
            log_message('warning', "[GoProAdapter] Sin h1.product_title en {$url}");
            return null;
        }

        $sku        = $this->nodeText($crawler, '.sku_wrapper .sku');
        $disponible = $crawler->filter('p.stock.in-stock')->count() > 0;

        // Precios dentro de .summary (evita capturar precios de productos relacionados)
        $precioNormal = null;
        $precioOferta = null;

        $summary = $crawler->filter('.summary');
        if ($summary->count() > 0) {
            $delEl = $summary->filter('del .woocommerce-Price-amount');
            $insEl = $summary->filter('ins .woocommerce-Price-amount');

            if ($delEl->count() > 0 && $insEl->count() > 0) {
                // Precio con oferta: tachado (normal) + oferta
                $precioNormal = $this->parsePrice($delEl->first()->text());
                $precioOferta = $this->parsePrice($insEl->first()->text());
            } else {
                // Precio sin oferta: primer .woocommerce-Price-amount en .summary
                $priceEl = $summary->filter('.woocommerce-Price-amount.amount');
                if ($priceEl->count() > 0) {
                    $precioNormal = $this->parsePrice($priceEl->first()->text());
                }
            }
        }

        $dto              = new ScrapedProductDTO($nombre);
        $dto->sku         = $sku;
        $dto->externalRef = $slug;
        $dto->precioNormal = $precioNormal;
        $dto->precioOferta = $precioOferta;
        $dto->disponible  = $disponible;
        $dto->url         = $url;

        return $dto;
    }

    /**
     * Extrae el slug del producto desde su URL.
     * Ej: "https://www.gopro.cl/producto/hero13-black/" → "hero13-black"
     */
    private function slugFromUrl(string $url): string
    {
        return trim(basename(rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/')));
    }
}
