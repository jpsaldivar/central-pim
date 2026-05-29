<?php

namespace App\Controllers;

use App\Models\MigrationLogModel;
use App\Services\ActualizarGeneralesService;
use App\Services\ConnectionManager;

class Woocommerce extends BaseController
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
        $generalesState = $this->logModel->getGeneralesCheckpoint();

        return view('woocommerce/index', [
            'title'            => 'WooCommerce',
            'woocommerce_ok'   => $this->connectionManager->isWooCommerceConfigured(),
            'jumpseller_ok'    => $this->connectionManager->isJumpsellerConfigured(),
            'migration_state'  => $this->logModel->getCheckpoint(),
            'inventario_state' => $this->logModel->getInventarioCheckpoint(),
            'generales_state'  => $generalesState,
            'generales_errors' => $this->logModel->getGeneralesErrors(10),
        ]);
    }

    public function actualizarGenerales()
    {
        if (!$this->connectionManager->isWooCommerceConfigured()) {
            return redirect()->to(site_url('woocommerce'))->with('error', 'WooCommerce no está configurado.');
        }

        $campos = $this->request->getPost('campos') ?? [];
        $campos = array_values(array_intersect($campos, ['nombre', 'marca']));

        if (empty($campos)) {
            return redirect()->to(site_url('woocommerce'))->with('error', 'Selecciona al menos un campo para actualizar.');
        }

        $autocontinue = $this->request->getPost('autocontinue') ? 'generales' : null;

        $service = $this->makeService();
        $result  = $service->start($campos);

        if (!$result['active']) {
            $s   = $result['summary'];
            $msg = "Completado: {$s['updated']} actualizado(s), {$s['skipped']} omitido(s), {$s['errors']} error(es).";
            return redirect()->to(site_url('woocommerce'))->with($result['summary']['errors'] > 0 ? 'error' : 'success', $msg);
        }

        $url = site_url('woocommerce') . ($autocontinue ? '?autocontinue=generales' : '');
        return redirect()->to($url);
    }

    public function reanudarGenerales()
    {
        if (!$this->connectionManager->isWooCommerceConfigured()) {
            return redirect()->to(site_url('woocommerce'));
        }

        $autocontinue = $this->request->getPost('autocontinue') === 'generales';

        $result = $this->makeService()->resume();

        if (!$result['active']) {
            $s   = $result['summary'];
            $msg = "Completado: {$s['updated']} actualizado(s), {$s['skipped']} omitido(s), {$s['errors']} error(es).";
            return redirect()->to(site_url('woocommerce'))->with($result['summary']['errors'] > 0 ? 'error' : 'success', $msg);
        }

        $url = site_url('woocommerce') . ($autocontinue ? '?autocontinue=generales' : '');
        return redirect()->to($url);
    }

    public function reiniciarGenerales()
    {
        $this->logModel->clearGeneralesCheckpoint();
        return redirect()->to(site_url('woocommerce'));
    }

    public function progresoGenerales()
    {
        $state = $this->logModel->getGeneralesCheckpoint();

        if (!$state) {
            return $this->response->setJSON(['active' => false, 'percent' => 100]);
        }

        $offset  = (int)($state['offset'] ?? 0);
        $total   = (int)($state['total']  ?? 1);
        $percent = $total > 0 ? (int)round(($offset / $total) * 100) : 0;

        return $this->response->setJSON([
            'active'      => true,
            'offset'      => $offset,
            'total'       => $total,
            'percent'     => $percent,
            'summary'     => $state['summary'] ?? [],
            'last_update' => $state['last_update'] ?? null,
        ]);
    }

    private function makeService(): ActualizarGeneralesService
    {
        return new ActualizarGeneralesService(
            $this->connectionManager->makeWooCommerceAdapter(),
            $this->connectionManager->getWooCommerceTiendaId(),
            $this->logModel
        );
    }
}
