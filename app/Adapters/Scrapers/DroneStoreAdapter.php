<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper para Dronestore Chile (https://dronestore.cl).
 *
 * Plataforma: PrestaShop con Elementor + Creative Elements (CE).
 * Las páginas de categoría son landing pages Elementor: todos los
 * productos están en una sola página, sin paginación estándar de PS.
 *
 * Identificadores disponibles:
 *   - externalRef: data-id-product del <article> (ID interno PrestaShop)
 *   - ean:         13 dígitos al final de la URL del producto
 *   - sku:         no está expuesto en el HTML (requeriría API PS)
 */
class DroneStoreAdapter extends BaseScraperAdapter
{
    public function getPlataforma(): string
    {
        return 'dronestore_scraper';
    }

    /**
     * @param  string[]            $urls  URLs de categorías configuradas en Config\Scrapers
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array
    {
        $productos = [];
        $seen      = []; // evitar duplicados por externalRef entre categorías

        foreach ($urls as $url) {
            $crawler = $this->fetch($url);
            if ($crawler === null) {
                log_message('warning', "[DroneStoreAdapter] No se pudo obtener {$url}");
                continue;
            }

            $crawler->filter('article[data-id-product]')->each(
                function (Crawler $article) use (&$productos, &$seen) {
                    $externalRef = $article->attr('data-id-product');
                    if (!$externalRef || isset($seen[$externalRef])) {
                        return;
                    }
                    $seen[$externalRef] = true;

                    // --- Nombre y URL del producto ---
                    $nombre      = null;
                    $productoUrl = null;

                    $article->filter('a[href*=".html"]')->each(
                        function (Crawler $a) use (&$nombre, &$productoUrl) {
                            if ($nombre !== null) {
                                return; // ya encontramos el nombre, saltar
                            }
                            $txt = trim($a->text());
                            if (
                                $txt === ''
                                || in_array($txt, ['Ver más', 'Vista rápida'], true)
                                || strlen($txt) >= 150
                            ) {
                                return;
                            }
                            $nombre      = $txt;
                            $productoUrl = $a->attr('href');
                        }
                    );

                    if ($nombre === null) {
                        return;
                    }

                    // --- Precios ---
                    $precioNormal = null;
                    $precioOferta = null;

                    $priceBox = $article->filter('.ce-product-prices');
                    if ($priceBox->count() > 0) {
                        $regularEl  = $priceBox->filter('.ce-product-price-regular');
                        $discountEl = $priceBox->filter('.ce-product-price.ce-has-discount');

                        if ($regularEl->count() > 0 && $discountEl->count() > 0) {
                            // Producto CON descuento: precio tachado + precio oferta
                            $precioNormal = $this->parsePrice($regularEl->first()->text());
                            $spanOferta   = $discountEl->filter('span');
                            $precioOferta = $spanOferta->count() > 0
                                ? $this->parsePrice($spanOferta->first()->text())
                                : null;
                        } else {
                            // Producto SIN descuento: un único precio
                            $normalSpan = $priceBox->filter('.ce-product-price:not(.ce-has-discount) span');
                            if ($normalSpan->count() > 0) {
                                $precioNormal = $this->parsePrice($normalSpan->first()->text());
                            }
                        }
                    }

                    // --- EAN desde la URL del producto (13 dígitos antes de .html) ---
                    $ean = null;
                    if ($productoUrl && preg_match('/(\d{13})\.html/', $productoUrl, $m)) {
                        $ean = $m[1];
                    }

                    // Disponibilidad inferida: si tiene precio visible, asumimos disponible.
                    // La disponibilidad real requeriría visitar la página individual.
                    $disponible = $precioNormal !== null;

                    $dto              = new ScrapedProductDTO($nombre);
                    $dto->externalRef = $externalRef;
                    $dto->ean         = $ean;
                    $dto->precioNormal = $precioNormal;
                    $dto->precioOferta = $precioOferta;
                    $dto->disponible  = $disponible;
                    $dto->url         = $productoUrl;

                    $productos[] = $dto;
                }
            );
        }

        return $productos;
    }
}
