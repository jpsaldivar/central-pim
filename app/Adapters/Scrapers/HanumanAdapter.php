<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper para Hanuman (https://consultas.hanuman.cl/stock/stock.php).
 *
 * El endpoint entrega una tabla HTML (id="productos3") con el catálogo completo
 * sin paginación. Los <tr> de datos vienen directo después de </thead> sin <tbody>.
 *
 * Estructura de columnas (8 en total):
 *   [0] MARCA          — texto en <abbr>
 *   [1] PRODUCTO        — texto en <abbr>
 *   [2] CÓDIGO BARRA    — EAN-13, generalmente vacío
 *   [3] CÓDIGO          — SKU del fabricante (ej: BY-WM8Pro-K1)
 *   [4] REFERENCIA      — imagen del producto (ignorada)
 *   [5] ENLACE          — <a href> a la página del producto en tienda.hanuman.cl
 *   [6] PRECIO PÚBLICO  — precio CLP en <abbr>
 *   [7] STOCK           — cantidad en <b>; "0" = sin stock
 */
class HanumanAdapter extends BaseScraperAdapter
{
    public function getPlataforma(): string
    {
        return 'hanuman_scraper';
    }

    /**
     * @param  string[]            $urls  Se pasa una sola URL (el endpoint de stock)
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array
    {
        $productos = [];
        $seen      = [];

        foreach ($urls as $url) {
            $crawler = $this->fetch($url);
            if ($crawler === null) {
                log_message('warning', "[HanumanAdapter] No se pudo obtener {$url}");
                continue;
            }

            // Los <tr> de datos van directo tras </thead> sin <tbody>
            // Usamos el id de la tabla y filtramos filas de encabezado
            $crawler->filter('#productos3 tr')->each(
                function (Crawler $row) use (&$productos, &$seen) {
                    // Ignorar filas de encabezado
                    if ($row->filter('th')->count() > 0) {
                        return;
                    }

                    $celdas = $row->filter('td');

                    // Necesitamos al menos 8 columnas
                    if ($celdas->count() < 8) {
                        return;
                    }

                    $codigo = trim($celdas->eq(3)->text());
                    $nombre = trim($celdas->eq(1)->text());

                    if ($nombre === '' || $codigo === '') {
                        return;
                    }

                    if (isset($seen[$codigo])) {
                        return;
                    }
                    $seen[$codigo] = true;

                    // EAN desde columna [2] (generalmente vacío)
                    $ean = trim($celdas->eq(2)->text());
                    $ean = ($ean !== '' && preg_match('/^\d{8,13}$/', $ean)) ? $ean : null;

                    // URL del producto en tienda.hanuman.cl
                    $enlaceNode = $celdas->eq(5)->filter('a');
                    $productoUrl = $enlaceNode->count() > 0 ? $enlaceNode->attr('href') : null;

                    $precioNormal = $this->parsePrice($celdas->eq(6)->text());

                    $stockText  = trim($celdas->eq(7)->text());
                    $disponible = $this->parseDisponible($stockText);

                    $dto              = new ScrapedProductDTO($nombre);
                    $dto->sku         = $codigo;
                    $dto->externalRef = $codigo;
                    $dto->ean         = $ean;
                    $dto->precioNormal = $precioNormal;
                    $dto->disponible  = $disponible;
                    $dto->url         = $productoUrl;

                    $productos[] = $dto;
                }
            );
        }

        return $productos;
    }

    /**
     * Determina disponibilidad a partir del texto de la columna STOCK.
     *
     * Valores observados:
     *   "0"   → sin stock → false
     *   "+30" → 30 o más  → true
     *   "15"  → exacto    → true
     */
    private function parseDisponible(string $texto): bool
    {
        $numero = (int)preg_replace('/[^0-9]/', '', $texto);
        return $numero > 0;
    }
}
