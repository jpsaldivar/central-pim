<?php

namespace App\DTOs;

/**
 * Producto obtenido por scraping de una tienda externa.
 * Representa un snapshot puntual: nombre, precios y disponibilidad
 * en el momento de la ejecución del scraper.
 */
class ScrapedProductDTO
{
    public string  $nombre;
    public ?string $sku         = null;  // SKU/referencia del fabricante (si está disponible)
    public ?string $ean         = null;  // EAN-13 (si está disponible en la URL o el DOM)
    public ?string $externalRef = null;  // ID o slug del producto en la plataforma origen
    public ?int    $precioNormal = null; // CLP entero sin decimales
    public ?int    $precioOferta = null; // CLP entero sin decimales (null si no hay oferta activa)
    public bool    $disponible  = true;
    public ?string $url         = null;

    public function __construct(string $nombre)
    {
        $this->nombre = $nombre;
    }
}
