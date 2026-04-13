<?php

namespace App\Controllers;

use App\Models\MigrationLogModel;
use App\Services\ConnectionManager;
use App\Services\CoreIntegrationService;
use App\Services\MigrationService;

/**
 * Manual migration controller.
 * Provides the UI to trigger and monitor Jumpseller → WooCommerce migrations.
 */
class Migraciones extends BaseController
{
    private ConnectionManager $connectionManager;
    private MigrationLogModel $logModel;

    public function __construct()
    {
        $this->connectionManager = new ConnectionManager();
        $this->logModel          = new MigrationLogModel();
    }

    /**
     * Main panel: shows configuration status, last migration stats, and the trigger form.
     */
    public function index(): string
    {
        return view('migraciones/index', [
            'title'              => 'Migraciones',
            'jumpseller_ok'      => $this->connectionManager->isJumpsellerConfigured(),
            'woocommerce_ok'     => $this->connectionManager->isWooCommerceConfigured(),
            'last_session_stats' => $this->logModel->getLastSessionStats(),
            'recent_logs'        => $this->logModel->getRecent(50),
        ]);
    }

    /**
     * Execute the migration synchronously.
     * For large catalogs (>500 products) this should be moved to a background queue.
     */
    public function ejecutar()
    {
        if (!$this->connectionManager->isJumpsellerConfigured()) {
            return redirect()->to(site_url('migraciones'))
                ->with('error', 'Credenciales de Jumpseller no configuradas en .env');
        }

        if (!$this->connectionManager->isWooCommerceConfigured()) {
            return redirect()->to(site_url('migraciones'))
                ->with('error', 'Credenciales de WooCommerce no configuradas en .env');
        }

        $jumpseller  = $this->connectionManager->makeJumpsellerAdapter();
        $woocommerce = $this->connectionManager->makeWooCommerceAdapter();
        $cis         = new CoreIntegrationService($this->logModel);
        $service     = new MigrationService($jumpseller, $woocommerce, $cis);

        $result = $service->run();

        $message = sprintf(
            'Migración completada en %ss — Creados: %d, Actualizados: %d, Omitidos: %d, Errores: %d.',
            $result['duration_seconds'],
            $result['created'],
            $result['updated'],
            $result['skipped'],
            $result['errors']
        );

        $flashKey = $result['errors'] > 0 ? 'error' : 'success';

        return redirect()->to(site_url('migraciones'))->with($flashKey, $message);
    }

    /**
     * Full paginated log viewer.
     */
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
}
