<?php

namespace App\Controllers;

use App\Models\ProductoModel;
use App\Models\ScraperProductoModel;
use App\Models\ScraperRunModel;
use App\Services\ScraperService;

class Scrapers extends BaseController
{
    private ScraperRunModel      $runModel;
    private ScraperProductoModel $productoModel;

    public function __construct()
    {
        $this->runModel      = model(ScraperRunModel::class);
        $this->productoModel = model(ScraperProductoModel::class);
    }

    /**
     * Dashboard: una fila por tienda scraper con su último run y estado.
     */
    public function index(): string
    {
        return view('scrapers/index', [
            'title'   => 'Scrapers',
            'tiendas' => $this->runModel->getResumenPorTienda(),
        ]);
    }

    /**
     * Dispara el scraping de una tienda y redirige al detalle del run.
     * En caso de error redirige al dashboard con mensaje.
     */
    public function run(int $tiendaId)
    {
        try {
            $service = new ScraperService();
            $runId   = $service->ejecutarRun($tiendaId);

            // Siempre redirigir al detalle del run; el estado 'error' se muestra ahí.
            $run = $this->runModel->find($runId);
            if ($run && $run['estado'] === 'error') {
                return redirect()
                    ->to(site_url("scrapers/show/{$runId}"))
                    ->with('error', 'El scraping falló. Ver detalles abajo.');
            }

            return redirect()
                ->to(site_url("scrapers/show/{$runId}"))
                ->with('success', 'Scraping completado correctamente.');

        } catch (\RuntimeException $e) {
            // Solo errores previos a la creación del run (tienda inexistente, run concurrente…)
            return redirect()
                ->to(site_url('scrapers'))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Detalle de un run: tabla de productos con diff de precio y vínculo al catálogo.
     */
    public function show(int $runId): string
    {
        $run = $this->runModel->find($runId);
        if (!$run) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(
                "Run #{$runId} no encontrado."
            );
        }

        $productos       = $this->productoModel->getProductosDelRun($runId);
        $sinVincular     = $this->productoModel->getSinVincular($runId);
        $catalogoOptions = $this->getCatalogoOptions();

        return view('scrapers/show', [
            'title'           => 'Scraper — Run #' . $runId,
            'run'             => $run,
            'productos'       => $productos,
            'sin_vincular'    => count($sinVincular),
            'catalogo_options' => $catalogoOptions,
        ]);
    }

    /**
     * Vincula manualmente un scraper_producto con un producto del catálogo interno.
     *
     * POST params:
     *   scraper_producto_id  int
     *   producto_id          int
     *   run_id               int  (para el redirect de vuelta)
     */
    public function link()
    {
        $scraperProductoId = (int)$this->request->getPost('scraper_producto_id');
        $productoId        = (int)$this->request->getPost('producto_id');
        $runId             = (int)$this->request->getPost('run_id');

        if (!$scraperProductoId || !$productoId || !$runId) {
            return redirect()
                ->to(site_url('scrapers'))
                ->with('error', 'Datos incompletos para vincular.');
        }

        $this->productoModel->vincular($scraperProductoId, $productoId);

        return redirect()
            ->to(site_url("scrapers/show/{$runId}"))
            ->with('success', 'Producto vinculado correctamente.');
    }

    /**
     * Devuelve la lista de productos internos para el dropdown de vinculación.
     * Formato: [ ['id' => int, 'label' => 'SKU — Nombre'] ]
     */
    private function getCatalogoOptions(): array
    {
        $rows = model(ProductoModel::class)
            ->select('id, sku, nombre')
            ->orderBy('nombre', 'ASC')
            ->findAll();

        return array_map(fn($p) => [
            'id'    => $p['id'],
            'label' => trim(($p['sku'] ? $p['sku'] . ' — ' : '') . $p['nombre']),
        ], $rows);
    }
}
