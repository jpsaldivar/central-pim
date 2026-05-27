<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper para Hanuman (https://consultas.hanuman.cl/stock/stock.php).
 *
 * El endpoint entrega una tabla HTML con el catálogo completo sin paginación.
 * Columnas: MARCA | PRODUCTO | CÓDIGO | REFERENCIA | PRECIO PÚBLICO | STOCK
 *
 * Identificadores disponibles:
 *   - sku/externalRef: columna CÓDIGO (ej: BY-WM8Pro-K1, ZOOM-H4nPro-BLK)
 *   - disponible:      columna STOCK — "0" = sin stock, "+30" o número > 0 = disponible
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

            // La tabla de productos está dentro del <tbody>
            $crawler->filter('table tbody tr')->each(
                function (Crawler $row) use (&$productos, &$seen) {
                    $celdas = $row->filter('td');

                    // Esperamos al menos 6 columnas: MARCA, PRODUCTO, CÓDIGO, REFERENCIA, PRECIO, STOCK
                    if ($celdas->count() < 6) {
                        return;
                    }

                    $codigo = trim($celdas->eq(2)->text());
                    $nombre = trim($celdas->eq(1)->text());

                    if ($nombre === '' || $codigo === '') {
                        return;
                    }

                    // Evitar duplicados por CÓDIGO
                    if (isset($seen[$codigo])) {
                        return;
                    }
                    $seen[$codigo] = true;

                    $precioText = trim($celdas->eq(4)->text());
                    $precioNormal = $this->parsePrice($precioText);

                    $stockText  = trim($celdas->eq(5)->text());
                    $disponible = $this->parseDisponible($stockText);

                    $dto              = new ScrapedProductDTO($nombre);
                    $dto->sku         = $codigo;
                    $dto->externalRef = $codigo;
                    $dto->precioNormal = $precioNormal;
                    $dto->disponible  = $disponible;

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
        // Quitar el "+" y espacios, dejar solo el número
        $numero = (int)preg_replace('/[^0-9]/', '', $texto);
        return $numero > 0;
    }
}
