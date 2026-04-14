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
