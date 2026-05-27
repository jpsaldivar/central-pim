<?php

namespace App\Adapters\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base para todos los adapters de scraping.
 *
 * Provee un cliente HTTP preconfigurado con headers realistas,
 * un método fetch() que devuelve un DomCrawler listo para usar,
 * throttle entre requests y parsing de precios en formato CLP.
 */
abstract class BaseScraperAdapter implements ScraperInterface
{
    protected Client $http;

    /** Milisegundos de pausa entre requests sucesivos al mismo sitio. */
    protected int $throttleMs = 1500;

    public function __construct()
    {
        $this->http = new Client([
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-CL,es;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
            ],
            'timeout'         => 20,
            'connect_timeout' => 10,
            'http_errors'     => false, // manejar errores HTTP manualmente
        ]);
    }

    /**
     * Descarga una URL y devuelve un DomCrawler listo para operar con
     * selectores CSS. Devuelve null si la respuesta no es 200.
     */
    protected function fetch(string $url): ?Crawler
    {
        try {
            $response = $this->http->get($url);

            if ($response->getStatusCode() !== 200) {
                log_message('warning', "[{$this->getPlataforma()}] HTTP {$response->getStatusCode()} al hacer GET {$url}");
                return null;
            }

            return new Crawler($response->getBody()->getContents(), $url);
        } catch (GuzzleException $e) {
            log_message('error', "[{$this->getPlataforma()}] Error al hacer GET {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Pausa entre requests para no saturar el servidor.
     * Se llama explícitamente antes de cada request adicional dentro de scrape().
     */
    protected function throttle(): void
    {
        usleep($this->throttleMs * 1000);
    }

    /**
     * Devuelve el texto de la primera coincidencia de $selector, o null si no existe.
     */
    protected function nodeText(Crawler $crawler, string $selector): ?string
    {
        $node = $crawler->filter($selector);
        return $node->count() > 0 ? trim($node->first()->text()) : null;
    }

    /**
     * Devuelve el valor de un atributo de la primera coincidencia de $selector, o null si no existe.
     */
    protected function nodeAttr(Crawler $crawler, string $selector, string $attr): ?string
    {
        $node = $crawler->filter($selector);
        return $node->count() > 0 ? $node->first()->attr($attr) : null;
    }

    /**
     * Convierte un string de precio chileno a entero CLP.
     *
     * Ejemplos aceptados:
     *   "$ 1.339.990"  → 1339990
     *   "$459.990"     → 459990
     *   "1.169.990"    → 1169990
     *   "$2.009.990,00"→ 2009990  (ignora centavos)
     *
     * Devuelve null si el string no contiene dígitos.
     */
    protected function parsePrice(string $text): ?int
    {
        // Eliminar símbolo de moneda, espacios y puntos de miles
        $cleaned = preg_replace('/[^0-9,]/', '', $text);

        if ($cleaned === '' || $cleaned === null) {
            return null;
        }

        // Eliminar parte decimal si viene con coma (ej: "990,00")
        $cleaned = explode(',', $cleaned)[0];

        $value = (int)$cleaned;
        return $value > 0 ? $value : null;
    }
}
