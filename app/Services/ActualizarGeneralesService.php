<?php

namespace App\Services;

use App\Adapters\WooCommerceAdapter;
use App\Models\MigrationLogModel;
use Config\Database;

/**
 * Actualiza campos generales (nombre, marca) en WooCommerce
 * tomando los datos desde la base de datos interna.
 * Procesa en páginas y guarda checkpoint para soportar timeouts.
 * No toca precios ni stock.
 *
 * Nota: `brands` no funciona en PUT individual (v3) — se envía via batch.
 */
class ActualizarGeneralesService
{
    private const PAGE_SIZE = 25;

    private WooCommerceAdapter $woo;
    private int $wooTiendaId;
    private MigrationLogModel $logModel;

    public function __construct(WooCommerceAdapter $woo, int $wooTiendaId, MigrationLogModel $logModel)
    {
        $this->woo         = $woo;
        $this->wooTiendaId = $wooTiendaId;
        $this->logModel    = $logModel;
    }

    /**
     * Inicia desde cero.
     * @param string[] $campos  'nombre', 'marca'
     */
    public function start(array $campos): array
    {
        $state = [
            'status'  => 'in_progress',
            'campos'  => $campos,
            'offset'  => 0,
            'total'   => $this->countProducts(),
            'summary' => ['updated' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        $this->logModel->saveGeneralesCheckpoint($state);
        return $this->processPage($state);
    }

    /**
     * Retoma desde el último checkpoint guardado.
     */
    public function resume(): array
    {
        $state = $this->logModel->getGeneralesCheckpoint();
        if (!$state) {
            return ['active' => false];
        }
        return $this->processPage($state);
    }

    private function processPage(array $state): array
    {
        set_time_limit(ENVIRONMENT === 'development' ? 0 : 120);

        $campos     = $state['campos'];
        $offset     = (int)$state['offset'];
        $total      = (int)$state['total'];
        $summary    = $state['summary'];
        $brandCache = [];
        $batchMarcas = []; // acumulados para enviar via batch al final

        $products = $this->fetchPage($offset, self::PAGE_SIZE);

        foreach ($products as $product) {
            $wooId = (int)$product['woo_id'];

            // Nombre: PUT individual funciona correctamente en v3
            if (in_array('nombre', $campos)) {
                $nombre = trim($product['nombre'] ?? '');
                if ($nombre !== '') {
                    $res = $this->woo->updateProduct($wooId, ['name' => $nombre]);
                    if ($res) {
                        $summary['updated']++;
                    } else {
                        $summary['errors']++;
                        $this->logError($product['nombre'], $wooId, $this->woo->getLastError());
                    }
                } else {
                    $summary['skipped']++;
                }
            }

            // Marca: acumular para batch (brands falla en PUT individual v3)
            if (in_array('marca', $campos)) {
                $marca = trim($product['marca_nombre'] ?? '');
                if ($marca !== '') {
                    if (!isset($brandCache[$marca])) {
                        $brandCache[$marca] = $this->woo->findOrCreateBrand($marca);
                    }
                    $brandId = $brandCache[$marca];
                    if ($brandId) {
                        $batchMarcas[] = ['id' => $wooId, 'brands' => [$brandId]];
                    } else {
                        $summary['skipped']++;
                    }
                } else {
                    $summary['skipped']++;
                }
            }
        }

        // Enviar marcas acumuladas via batch
        if (!empty($batchMarcas)) {
            $batchResult = $this->woo->batchUpdateByIds($batchMarcas);
            $summary['updated'] += $batchResult['updated'];
            $summary['errors']  += count($batchResult['errors']);
            foreach ($batchResult['errors'] as $err) {
                $this->logError('batch_marcas', 0, $err);
            }
        }

        $newOffset = $offset + count($products);
        $done      = $newOffset >= $total || count($products) === 0;

        if ($done) {
            $this->logModel->clearGeneralesCheckpoint();
            return ['active' => false, 'summary' => $summary, 'percent' => 100];
        }

        $newState = [
            'status'  => 'in_progress',
            'campos'  => $campos,
            'offset'  => $newOffset,
            'total'   => $total,
            'summary' => $summary,
        ];
        $this->logModel->saveGeneralesCheckpoint($newState);

        $percent = (int)round(($newOffset / max($total, 1)) * 100);
        return ['active' => true, 'summary' => $summary, 'offset' => $newOffset, 'total' => $total, 'percent' => $percent];
    }

    private function logError(string $nombre, int $wooId, ?string $msg): void
    {
        $this->logModel->insert([
            'tipo'            => 'generales_sync',
            'sku'             => mb_substr($nombre, 0, 100),
            'nombre_producto' => $wooId ? "woo_id={$wooId}" : '',
            'accion'          => 'update_failed',
            'estado'          => 'error',
            'mensaje'         => mb_substr($msg ?? 'Error desconocido', 0, 500),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function fetchPage(int $offset, int $limit): array
    {
        $db = Database::connect();
        return $db->table('productos p')
            ->select('p.id, p.nombre, m.nombre AS marca_nombre, pt.external_id AS woo_id')
            ->join('marcas m',           'm.id = p.marca_id', 'left')
            ->join('producto_tienda pt', "pt.producto_id = p.id AND pt.tienda_id = {$this->wooTiendaId}", 'inner')
            ->where('pt.external_id IS NOT NULL')
            ->where('pt.external_id !=', '')
            ->limit($limit, $offset)
            ->get()->getResultArray();
    }

    private function countProducts(): int
    {
        $db = Database::connect();
        return $db->table('productos p')
            ->join('producto_tienda pt', "pt.producto_id = p.id AND pt.tienda_id = {$this->wooTiendaId}", 'inner')
            ->where('pt.external_id IS NOT NULL')
            ->where('pt.external_id !=', '')
            ->countAllResults();
    }
}
