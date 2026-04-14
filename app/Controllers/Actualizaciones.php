<?php
namespace App\Controllers;

use App\Models\ProductoModel;
use App\Models\TiendaModel;
use CodeIgniter\Controller;

class Actualizaciones extends Controller
{
    protected ProductoModel $model;

    public function __construct()
    {
        $this->model = new ProductoModel();
    }

    public function precios()
    {
        if ($this->request->getMethod() === 'post') {
            $precios          = $this->request->getPost('precio')           ?? [];
            $preciosOferta    = $this->request->getPost('precio_oferta')    ?? [];
            $valoresEsp       = $this->request->getPost('valor_esp')        ?? [];
            $valoresOfertaEsp = $this->request->getPost('valor_oferta_esp') ?? [];

            $count = $this->model->bulkUpdatePrecios($precios, $preciosOferta, $valoresEsp, $valoresOfertaEsp);
            return redirect()->back()->with('success', "{$count} producto(s) actualizados.");
        }

        return $this->buildView('precios');
    }

    public function stock()
    {
        if ($this->request->getMethod() === 'post') {
            $stocks    = $this->request->getPost('stock_general') ?? [];
            $stocksEsp = $this->request->getPost('stock_esp')     ?? [];

            $count = $this->model->bulkUpdateStock($stocks, $stocksEsp);
            return redirect()->back()->with('success', "{$count} producto(s) actualizados.");
        }

        return $this->buildView('stock');
    }

    private function buildView(string $vista): string
    {
        $allowed = [25, 50, 100, 500, 1000];
        $perPage = (int)($this->request->getGet('per_page') ?? 50);
        $perPage = in_array($perPage, $allowed) ? $perPage : 50;

        $tiendas  = (new TiendaModel())->findAll();
        $productos = $this->model->getWithRelations($perPage);
        $pager    = $this->model->pager;

        $ids   = array_column($productos, 'id');
        $rawPT = $this->model->getAllProductoTiendas($ids);

        $productoTiendas = [];
        foreach ($rawPT as $pt) {
            $productoTiendas[$pt['producto_id']][$pt['tienda_id']] = $pt;
        }

        $titles = [
            'precios' => 'Actualizar Precios',
            'stock'   => 'Actualizar Stock',
        ];

        return view("actualizaciones/{$vista}", [
            'title'            => $titles[$vista],
            'productos'        => $productos,
            'tiendas'          => $tiendas,
            'producto_tiendas' => $productoTiendas,
            'pager'            => $pager,
            'perPage'          => $perPage,
        ]);
    }
}
