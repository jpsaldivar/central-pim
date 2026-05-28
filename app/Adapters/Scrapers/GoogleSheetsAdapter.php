<?php

namespace App\Adapters\Scrapers;

use App\DTOs\ScrapedProductDTO;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Adapter genérico para proveedores que publican su lista de precios
 * en Google Sheets como hoja pública.
 *
 * Cada tienda con plataforma "google_sheets_*" usa este adapter.
 * La URL que recibe en scrape() es la URL de exportación CSV:
 *   https://docs.google.com/spreadsheets/d/{ID}/export?format=csv&gid={GID}
 *
 * Columnas reconocidas (busca por nombre de cabecera, insensible a espacios):
 *   - "SKU" o "SKU (Links oficiales)"  → nombre + sku + externalRef
 *   - "P. público con iva"             → precioNormal (CLP con IVA incluido)
 *   - "P. Público Neto"                → precioNormal fallback si no existe la anterior
 *   - "STOCK"                          → disponible (> 0 = true)
 *
 * Si el proveedor usa nombres de columna distintos, agregar el alias en
 * self::resolveCol() sin tocar el resto del adapter.
 */
class GoogleSheetsAdapter extends BaseScraperAdapter
{
    public function getPlataforma(): string
    {
        // Plataforma base; las tiendas usan google_sheets_{slug}
        return 'google_sheets';
    }

    /**
     * @param  string[]            $urls  URLs de exportación CSV de Google Sheets
     * @return ScrapedProductDTO[]
     */
    public function scrape(array $urls): array
    {
        $productos = [];
        $seen      = [];

        foreach ($urls as $url) {
            $csv = $this->fetchCsv($url);
            if ($csv === null) {
                continue;
            }

            if (trim($csv) === '') {
                log_message('warning', "[GoogleSheetsAdapter] CSV vacío: {$url}");
                continue;
            }

            // Usar fgetcsv() sobre un stream de memoria para manejar correctamente
            // campos multi-línea (descripciones con saltos de línea dentro de comillas).
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $csv);
            rewind($stream);

            $headers   = null;
            $idxSku    = null;
            $idxPrecio = null;
            $idxStock  = null;

            while (($cols = fgetcsv($stream)) !== false) {
                // Primera fila válida = cabeceras
                if ($headers === null) {
                    $headers   = array_map('trim', $cols);
                    $idxSku    = $this->resolveCol($headers, ['SKU (Links oficiales)', 'SKU', 'Código', 'Modelo']);
                    $idxPrecio = $this->resolveCol($headers, ['P. público con iva', 'P. Público con IVA', 'Precio Público', 'P. Público Neto']);
                    $idxStock  = $this->resolveCol($headers, ['STOCK', 'Stock', 'Existencia']);

                    if ($idxSku === null || $idxPrecio === null || $idxStock === null) {
                        log_message('error', "[GoogleSheetsAdapter] Columnas requeridas no encontradas en {$url}. " .
                            "Cabeceras: " . implode(', ', $headers));
                        break;
                    }
                    continue;
                }

                $cols = array_map('trim', $cols);

                $sku    = $cols[$idxSku]    ?? '';
                $precio = $cols[$idxPrecio] ?? '';
                $stock  = $cols[$idxStock]  ?? '0';

                // Fila vacía o sin SKU (separadores, totales, etc.)
                if ($sku === '') {
                    continue;
                }

                if (isset($seen[$sku])) {
                    continue;
                }
                $seen[$sku] = true;

                $precioNormal = $this->parsePrice($precio);
                $disponible   = ((int)preg_replace('/[^0-9]/', '', $stock)) > 0;
                $skuSlug      = 'slug-' . $this->toSlug($sku);

                $dto               = new ScrapedProductDTO($sku);
                $dto->sku          = $skuSlug;
                $dto->externalRef  = $skuSlug;
                $dto->precioNormal = $precioNormal;
                $dto->disponible   = $disponible;

                $productos[] = $dto;
            }

            fclose($stream);
        }

        return $productos;
    }

    /**
     * Descarga el CSV desde Google Sheets y devuelve su contenido como string.
     * Usa Guzzle directamente (sin DomCrawler).
     */
    private function fetchCsv(string $url): ?string
    {
        try {
            $response = $this->http->get($url, [
                'allow_redirects' => true,
                'headers'         => ['Accept' => 'text/csv,text/plain,*/*'],
            ]);

            if ($response->getStatusCode() !== 200) {
                log_message('warning', "[GoogleSheetsAdapter] HTTP {$response->getStatusCode()} al obtener {$url}");
                return null;
            }

            return $response->getBody()->getContents();

        } catch (GuzzleException $e) {
            log_message('error', "[GoogleSheetsAdapter] Error al obtener {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera un slug a partir de un texto.
     *
     * Ejemplos:
     *   "WiTalk DMH"        → "witalk-dmh"
     *   "Saramonic Air-01"  → "saramonic-air-01"
     *   "Blink 500 B1 (Rx + Tx)" → "blink-500-b1-rx-tx"
     */
    private function toSlug(string $text): string
    {
        // Transliterar caracteres con tilde/acento a su equivalente ASCII
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?? $text;
        $text = mb_strtolower($text);
        // Reemplazar cualquier carácter que no sea letra, número o guion por "-"
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        // Limpiar guiones al inicio y final
        return trim($text, '-');
    }

    /**
     * Busca el índice de la primera cabecera que coincida con alguno de los alias
     * provistos. La comparación es insensible a mayúsculas y espacios extremos.
     *
     * @param  string[] $headers  Cabeceras del CSV (ya trimmeadas)
     * @param  string[] $aliases  Lista de nombres posibles, en orden de preferencia
     * @return int|null
     */
    private function resolveCol(array $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            foreach ($headers as $i => $header) {
                if (mb_strtolower($header) === mb_strtolower($alias)) {
                    return $i;
                }
            }
        }
        return null;
    }
}
