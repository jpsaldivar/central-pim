<?php

namespace App\Controllers;

use App\Models\MigrationLogModel;
use App\Models\ProductoModel;
use App\Services\ConnectionManager;
use App\Services\CoreIntegrationService;
use App\Services\MigrationService;

class Migraciones extends BaseController
{
    private ConnectionManager $connectionManager;
    private MigrationLogModel $logModel;

    public function __construct()
    {
        $this->connectionManager = new ConnectionManager();
        $this->logModel          = new MigrationLogModel();
    }

    public function index(): string
    {
        return view('migraciones/index', [
            'title'              => 'Migraciones',
            'jumpseller_ok'      => $this->connectionManager->isJumpsellerConfigured(),
            'woocommerce_ok'     => $this->connectionManager->isWooCommerceConfigured(),
            'last_session_stats' => $this->logModel->getLastSessionStats(),
            'recent_logs'        => $this->logModel->getRecent(50),
            'migration_state'    => $this->logModel->getCheckpoint(),
        ]);
    }

    public function ejecutar()
    {
        if ($error = $this->checkCredentials()) {
            return redirect()->to(site_url('migraciones'))->with('error', $error);
        }

        $result = $this->makeService()->run();

        return redirect()->to(site_url('migraciones'))
            ->with($result['errors'] > 0 ? 'error' : 'success', $this->formatResult($result));
    }

    public function reanudar()
    {
        if ($this->checkCredentials()) {
            return redirect()->to(site_url('migraciones'));
        }

        $service = $this->makeService();

        if (!$service->getState()) {
            return redirect()->to(site_url('migraciones'));
        }

        $result = $service->resume();

        if ($result === null) {
            return redirect()->to(site_url('migraciones'));
        }

        return redirect()->to(site_url('migraciones'))
            ->with($result['errors'] > 0 ? 'error' : 'success', $this->formatResult($result));
    }

    public function reiniciar()
    {
        $this->makeService()->clearState();

        return redirect()->to(site_url('migraciones'))
            ->with('success', 'Estado de migración limpiado. Puedes iniciar una nueva migración.');
    }

    /**
     * Sync a single product (by internal producto_id) from Jumpseller to WooCommerce.
     * Runs the same 3-phase logic as the full migration but for one product.
     */
    public function syncProducto(int $productoId)
    {
        if ($error = $this->checkCredentials()) {
            return redirect()->back()->with('error', $error);
        }

        $db     = \Config\Database::connect();
        $jsTiendaId = $this->connectionManager->getJumpsellerTiendaId();

        // Look up the Jumpseller external_id for this product
        $row = $db->table('producto_tienda')
            ->where('producto_id', $productoId)
            ->where('tienda_id', $jsTiendaId)
            ->get()->getRowArray();

        if (!$row || empty($row['external_id'])) {
            return redirect()->back()
                ->with('error', "Producto #{$productoId} no tiene external_id de Jumpseller vinculado.");
        }

        $jsAdapter = $this->connectionManager->makeJumpsellerAdapter();
        $dto       = $jsAdapter->fetchProductById((int)$row['external_id']);

        if (!$dto) {
            return redirect()->back()
                ->with('error', "No se pudo obtener el producto #{$row['external_id']} desde Jumpseller.");
        }

        // Usar stock local en lugar del stock de Jumpseller, ya que el usuario puede
        // haber modificado stock/stock_ilimitado en el catálogo interno.
        $localProducto = (new \App\Models\ProductoModel())->find($productoId);
        if ($localProducto) {
            $dto->manageStock   = !(bool)($localProducto['stock_ilimitado'] ?? false);
            $dto->stockQuantity = $dto->manageStock ? (int)($localProducto['stock_general'] ?? 0) : 0;
        }

        $cis = new CoreIntegrationService(
            $this->logModel,
            new ProductoModel(),
            $jsTiendaId,
            $this->connectionManager->getWooCommerceTiendaId()
        );

        $result = $cis->syncToWooCommerce([$dto], $this->connectionManager->makeWooCommerceAdapter());

        $msg = sprintf(
            'Producto sincronizado — Creados: %d, Actualizados: %d, Errores: %d.',
            $result['created'],
            $result['updated'],
            $result['errors']
        );

        return redirect()->back()
            ->with($result['errors'] > 0 ? 'error' : 'success', $msg);
    }

    /**
     * Pull a single product from Jumpseller and update the internal catalog only.
     * Does NOT push to WooCommerce.
     */
    public function syncDesdeJumpseller(int $productoId)
    {
        if (!$this->connectionManager->isJumpsellerConfigured()) {
            return redirect()->back()
                ->with('error', 'Credenciales de Jumpseller no configuradas en .env');
        }

        $db         = \Config\Database::connect();
        $jsTiendaId = $this->connectionManager->getJumpsellerTiendaId();

        $row = $db->table('producto_tienda')
            ->where('producto_id', $productoId)
            ->where('tienda_id', $jsTiendaId)
            ->get()->getRowArray();

        if (!$row || empty($row['external_id'])) {
            return redirect()->back()
                ->with('error', "Producto #{$productoId} no tiene external_id de Jumpseller vinculado.");
        }

        $jsAdapter = $this->connectionManager->makeJumpsellerAdapter();
        $dto       = $jsAdapter->fetchProductById((int)$row['external_id']);

        if (!$dto) {
            return redirect()->back()
                ->with('error', "No se pudo obtener el producto #{$row['external_id']} desde Jumpseller.");
        }

        $productoModel = new \App\Models\ProductoModel();
        $result        = $productoModel->upsertFromDto($dto, $jsTiendaId);

        if (!$result['id']) {
            return redirect()->back()
                ->with('error', 'Error al actualizar el producto en el catálogo interno.');
        }

        $action = $result['isNew'] ? 'creado' : 'actualizado';
        return redirect()->back()
            ->with('success', "Producto {$action} correctamente desde Jumpseller.");
    }

    public function syncSkus()
    {
        if (!$this->connectionManager->isJumpsellerConfigured()) {
            return redirect()->to(site_url('migraciones'))
                ->with('error', 'Credenciales de Jumpseller no configuradas en .env');
        }

        $db             = \Config\Database::connect();
        $adapter        = $this->connectionManager->makeJumpsellerAdapter();
        $tiendaId       = $this->connectionManager->getJumpsellerTiendaId();
        $limit          = 50;
        $page           = 1;
        $updated        = 0;
        $skipped        = 0;
        $noMatch        = 0;
        $duplicates     = 0;

        do {
            $products = $adapter->fetchProducts($page, $limit);

            foreach ($products as $dto) {
                if (empty($dto->sku)) {
                    $skipped++;
                    continue;
                }

                // Buscar producto interno por external_id de Jumpseller
                $row = $db->table('producto_tienda')
                    ->where('tienda_id', $tiendaId)
                    ->where('external_id', (string)$dto->sourceId)
                    ->get()->getRowArray();

                if (!$row) {
                    $noMatch++;
                    continue;
                }

                // Solo actualizar si el SKU está vacío
                $producto = $db->table('productos')
                    ->where('id', $row['producto_id'])
                    ->where('(sku IS NULL OR sku = "")', null, false)
                    ->get()->getRowArray();

                if (!$producto) {
                    $skipped++;
                    continue;
                }

                // Verificar que el SKU no esté ya usado por otro producto
                $skuTaken = $db->table('productos')
                    ->where('sku', $dto->sku)
                    ->where('id !=', $row['producto_id'])
                    ->countAllResults() > 0;

                if ($skuTaken) {
                    $duplicates++;
                    continue;
                }

                $db->table('productos')
                    ->where('id', $row['producto_id'])
                    ->update(['sku' => $dto->sku]);
                $updated++;
            }

            $page++;
        } while (count($products) === $limit);

        return redirect()->to(site_url('migraciones'))
            ->with('success', "SKUs sincronizados — Actualizados: {$updated}, Sin SKU en origen: {$skipped}, Sin coincidencia interna: {$noMatch}, SKU duplicado (omitido): {$duplicates}.");
    }

    public function progreso()
    {
        $checkpoint = $this->logModel->getCheckpoint();

        if (!$checkpoint) {
            return $this->response->setJSON(['active' => false]);
        }

        $total   = max((int)($checkpoint['total_pages'] ?? 1), 1);
        $current = (int)($checkpoint['last_completed_page'] ?? 0);

        return $this->response->setJSON([
            'active'              => true,
            'percent'             => (int)round(($current / $total) * 100),
            'last_completed_page' => $current,
            'total_pages'         => $total,
            'total_products'      => $checkpoint['total_products'] ?? 0,
            'summary'             => $checkpoint['summary'] ?? [],
            'last_update'         => $checkpoint['last_update'] ?? null,
        ]);
    }

    public function logs(): string
    {
        $page   = (int)($this->request->getGet('page') ?? 1);
        $limit  = 100;
        $offset = ($page - 1) * $limit;

        $total = $this->logModel->countAllResults();
        $logs  = $this->logModel->getRecent($limit, $offset);

        return view('migraciones/logs', [
            'title'       => 'Logs de Migración',
            'logs'        => $logs,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int)ceil($total / $limit),
        ]);
    }

    // -------------------------------------------------------------------------

    private function makeService(): MigrationService
    {
        $cis = new CoreIntegrationService(
            $this->logModel,
            new ProductoModel(),
            $this->connectionManager->getJumpsellerTiendaId(),
            $this->connectionManager->getWooCommerceTiendaId()
        );

        return new MigrationService(
            $this->connectionManager->makeJumpsellerAdapter(),
            $this->connectionManager->makeWooCommerceAdapter(),
            $cis
        );
    }

    private function checkCredentials(): ?string
    {
        if (!$this->connectionManager->isJumpsellerConfigured()) {
            return 'Credenciales de Jumpseller no configuradas en .env';
        }
        if (!$this->connectionManager->isWooCommerceConfigured()) {
            return 'Credenciales de WooCommerce no configuradas en .env';
        }
        return null;
    }

    private function formatResult(array $result): string
    {
        return sprintf(
            'Migración completada en %ss — Creados: %d, Actualizados: %d, Omitidos: %d, Errores: %d.',
            $result['duration_seconds'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['errors']
        );
    }
}
