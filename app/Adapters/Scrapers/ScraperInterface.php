<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;

/**
 * Contrato para todos los adapters de scraping.
 *
 * Cada implementación conoce la estructura HTML de un sitio específico
 * pero es agnóstica de qué categorías están activas: las recibe como
 * parámetro desde Config\Scrapers a través de ScraperService.
 */
interface ScraperInterface
{
    /**
     * Scrapea las URLs de categorías indicadas y devuelve todos los
     * productos encontrados normalizados en DTOs.
     *
     * @param  string[]            $urls  URLs de categorías a procesar
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array;

    /**
     * Identificador de la plataforma.
     * Debe coincidir con tiendas.plataforma y con la clave en Config\Scrapers.
     */
    public function getPlataforma(): string;
}
