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

            $lines  = array_filter(explode("\n", $csv), fn($l) => trim($l) !== '');
            $lines  = array_values($lines);

            if (empty($lines)) {
                log_message('warning', "[GoogleSheetsAdapter] CSV vacío: {$url}");
                continue;
            }

            // Primera fila = cabeceras
            $headers = str_getcsv(array_shift($lines));
            $headers = array_map('trim', $headers);

            $idxSku    = $this->resolveCol($headers, ['SKU (Links oficiales)', 'SKU', 'Código', 'Modelo']);
            $idxPrecio = $this->resolveCol($headers, ['P. público con iva', 'P. Público con IVA', 'Precio Público', 'P. Público Neto']);
            $idxStock  = $this->resolveCol($headers, ['STOCK', 'Stock', 'Existencia']);

            if ($idxSku === null || $idxPrecio === null || $idxStock === null) {
                log_message('error', "[GoogleSheetsAdapter] No se encontraron columnas requeridas en {$url}. " .
                    "Cabeceras disponibles: " . implode(', ', $headers));
                continue;
            }

            foreach ($lines as $line) {
                $cols = str_getcsv($line);
                $cols = array_map('trim', $cols);

                $sku    = $cols[$idxSku]    ?? '';
                $precio = $cols[$idxPrecio] ?? '';
                $stock  = $cols[$idxStock]  ?? '0';

                // Fila vacía o sin SKU (filas de separación, totales, etc.)
                if ($sku === '') {
                    continue;
                }

                if (isset($seen[$sku])) {
                    continue;
                }
                $seen[$sku] = true;

                $precioNormal = $this->parsePrice($precio);
                $disponible   = ((int)preg_replace('/[^0-9]/', '', $stock)) > 0;

                $dto              = new ScrapedProductDTO($sku);
                $dto->sku         = $sku;
                $dto->externalRef = $sku;
                $dto->precioNormal = $precioNormal;
                $dto->disponible  = $disponible;

                $productos[] = $dto;
            }
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
