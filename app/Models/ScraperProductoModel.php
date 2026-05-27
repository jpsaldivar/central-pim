<?php

namespace App\Models;

use App\DTOs\ScrapedProductDTO;
use CodeIgniter\Model;

class ScraperProductoModel extends Model
{
    protected $table      = 'scraper_productos';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'run_id',
        'tienda_id',
        'external_ref',
        'ean',
        'sku',
        'nombre',
        'url',
        'precio_normal',
        'precio_oferta',
        'disponible',
        'producto_id',
        'created_at',
    ];

    /**
     * Inserta un lote de DTOs scrapeados para un run y tienda dados.
     * Devuelve el número de filas insertadas.
     */
    public function insertarLote(array $dtos, int $runId, int $tiendaId): int
    {
        if (empty($dtos)) {
            return 0;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($dtos as $dto) {
            if (!$dto instanceof ScrapedProductDTO) {
                continue;
            }

            $rows[] = [
                'run_id'        => $runId,
                'tienda_id'     => $tiendaId,
                'external_ref'  => $dto->externalRef,
                'ean'           => $dto->ean,
                'sku'           => $dto->sku,
                'nombre'        => mb_substr($dto->nombre, 0, 300),
                'url'           => $dto->url ? mb_substr($dto->url, 0, 500) : null,
                'precio_normal' => $dto->precioNormal,
                'precio_oferta' => $dto->precioOferta,
                'disponible'    => $dto->disponible ? 1 : 0,
                'producto_id'   => null,
                'created_at'    => $now,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        $this->insertBatch($rows);
        return count($rows);
    }

    /**
     * Vincula manualmente un scraper_producto con un producto del catálogo interno.
     */
    public function vincular(int $scraperProductoId, int $productoId): void
    {
        $this->update($scraperProductoId, ['producto_id' => $productoId]);
    }

    /**
     * Auto-matching por EAN o SKU para todos los productos de un run.
     *
     * Busca en el catálogo interno (productos.sku) y actualiza producto_id
     * cuando encuentra coincidencia. El EAN tiene precedencia sobre el SKU.
     *
     * Devuelve el número de vínculos creados automáticamente.
     */
    public function autoMatchPorEanOSku(int $runId): int
    {
        $db      = \Config\Database::connect();
        $matched = 0;

        $pendientes = $db->table('scraper_productos')
            ->where('run_id', $runId)
            ->where('producto_id IS NULL')
            ->where('(ean IS NOT NULL OR sku IS NOT NULL)')
            ->get()->getResultArray();

        foreach ($pendientes as $sp) {
            $productoId = null;

            // 1. Buscar por EAN (guardado en productos.sku cuando coincide)
            if (!empty($sp['ean'])) {
                $row = $db->table('productos')
                    ->where('sku', $sp['ean'])
                    ->get()->getRowArray();
                if ($row) {
                    $productoId = (int)$row['id'];
                }
            }

            // 2. Buscar por SKU exacto si el EAN no dio resultado
            if (!$productoId && !empty($sp['sku'])) {
                $row = $db->table('productos')
                    ->where('sku', $sp['sku'])
                    ->get()->getRowArray();
                if ($row) {
                    $productoId = (int)$row['id'];
                }
            }

            if ($productoId) {
                $db->table('scraper_productos')
                    ->where('id', (int)$sp['id'])
                    ->update(['producto_id' => $productoId]);
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * Devuelve los productos de un run con el precio del mismo producto
     * en el run anterior de esa tienda (para mostrar el diff en la vista).
     */
    public function getProductosDelRun(int $runId): array
    {
        $db = \Config\Database::connect();

        return $db->query("
            SELECT
                sp.*,
                p.nombre        AS producto_nombre,
                p.precio        AS precio_tecnofoto,
                prev.precio_normal AS precio_normal_anterior
            FROM scraper_productos sp
            LEFT JOIN productos p
                ON p.id = sp.producto_id
            LEFT JOIN scraper_productos prev
                ON  prev.producto_id  = sp.producto_id
                AND prev.producto_id  IS NOT NULL
                AND prev.tienda_id    = sp.tienda_id
                AND prev.run_id = (
                    SELECT MAX(sp2.run_id)
                    FROM scraper_productos sp2
                    INNER JOIN scraper_runs sr2 ON sr2.id = sp2.run_id
                    WHERE sp2.tienda_id   = sp.tienda_id
                      AND sp2.producto_id = sp.producto_id
                      AND sp2.run_id      < sp.run_id
                      AND sr2.estado      = 'completado'
                )
            WHERE sp.run_id = ?
            ORDER BY sp.nombre ASC
        ", [$runId])->getResultArray();
    }

    /**
     * Devuelve todos los productos no vinculados de un run (producto_id IS NULL).
     * Útil para la vista de vinculación manual.
     */
    public function getSinVincular(int $runId): array
    {
        return $this->where('run_id', $runId)
            ->where('producto_id IS NULL')
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }
}
