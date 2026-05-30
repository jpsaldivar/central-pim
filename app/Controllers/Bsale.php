<?php

namespace App\Controllers;

use App\Adapters\BsaleAdapter;
use App\Models\BsaleDocumentModel;
use App\Models\BsaleVariantMapModel;
use App\Services\BsaleDocumentService;
use Config\Bsale as BsaleConfig;

/**
 * UI de administración del módulo Bsale.
 */
class Bsale extends BaseController
{
    private BsaleDocumentModel  $documentModel;
    private BsaleVariantMapModel $variantMapModel;
    private BsaleConfig          $config;

    public function __construct()
    {
        $this->documentModel   = new BsaleDocumentModel();
        $this->variantMapModel = new BsaleVariantMapModel();
        $this->config          = new BsaleConfig();
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    /**
     * GET /bsale — Dashboard: últimos documentos y estadísticas.
     */
    public function index(): string
    {
        $documentos    = $this->documentModel->getRecientes(50);
        $estadisticas  = $this->documentModel->getEstadisticas();
        $configurado   = !empty($this->config->accessToken)
                      && $this->config->officeId > 0
                      && ($this->config->boletaTypeId > 0 || $this->config->facturaTypeId > 0);

        return view('bsale/index', [
            'title'        => 'Bsale — Documentos',
            'documentos'   => $documentos,
            'stats'        => $estadisticas,
            'configurado'  => $configurado,
        ]);
    }

    // -------------------------------------------------------------------------
    // Detalle de documento
    // -------------------------------------------------------------------------

    /**
     * GET /bsale/show/{id} — Detalle de un documento individual.
     */
    public function show(int $id): string
    {
        $documento = $this->documentModel->find($id);
        if ($documento === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound("Documento #{$id} no encontrado.");
        }

        return view('bsale/show', [
            'title'         => "Documento Bsale #{$id}",
            'documento'     => $documento,
            'boletaTypeId'  => $this->config->boletaTypeId,
            'facturaTypeId' => $this->config->facturaTypeId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Emisión manual de documento
    // -------------------------------------------------------------------------

    /**
     * POST /bsale/emitir/{id} — Emite el documento en Bsale eligiendo boleta o factura.
     * Válido para documentos en estado 'pendiente' o 'error'.
     */
    public function emitir(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $documento = $this->documentModel->find($id);
        if ($documento === null) {
            return redirect()->to('/bsale')->with('error', 'Documento no encontrado.');
        }

        if (!in_array($documento['estado'], ['pendiente', 'error'], true)) {
            return redirect()->to("/bsale/show/{$id}")->with('error', 'Solo se pueden emitir documentos en estado pendiente o error.');
        }

        $documentTypeId = (int) $this->request->getPost('document_type_id');
        if ($documentTypeId <= 0) {
            return redirect()->to("/bsale/show/{$id}")->with('error', 'Tipo de documento inválido.');
        }

        try {
            $service = new BsaleDocumentService(
                adapter:       new BsaleAdapter($this->config->accessToken),
                variantMap:    $this->variantMapModel,
                documentModel: $this->documentModel,
                config:        $this->config,
            );
            $service->emitirDocumento($id, $documentTypeId);

            return redirect()->to("/bsale/show/{$id}")->with('success', 'Documento emitido exitosamente en Bsale.');
        } catch (\RuntimeException $e) {
            return redirect()->to("/bsale/show/{$id}")->with('error', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Mapeo de variantes
    // -------------------------------------------------------------------------

    /**
     * GET /bsale/variant-map — Lista el mapeo SKU ↔ bsale_variant_id.
     */
    public function variantMap(): string
    {
        return view('bsale/variant_map', [
            'title'   => 'Bsale — Mapeo de Variantes',
            'mapeos'  => $this->variantMapModel->getTodos(),
        ]);
    }

    /**
     * POST /bsale/variant-map — Guarda o actualiza un mapeo.
     */
    public function saveMap(): \CodeIgniter\HTTP\RedirectResponse
    {
        $wooProductId   = (int) $this->request->getPost('woo_product_id');
        $sku            = trim((string) $this->request->getPost('sku'));
        $bsaleVariantId = (int) $this->request->getPost('bsale_variant_id');
        $nombre         = trim((string) $this->request->getPost('bsale_product_name'));

        if ($wooProductId <= 0 || empty($sku) || $bsaleVariantId <= 0) {
            return redirect()->to('/bsale/variant-map')->with('error', 'Todos los campos son obligatorios.');
        }

        $this->variantMapModel->upsert($wooProductId, $sku, $bsaleVariantId, $nombre);

        return redirect()->to('/bsale/variant-map')->with('success', "Mapeo para SKU '{$sku}' guardado.");
    }

    /**
     * GET /bsale/search-variant?sku=X — AJAX: busca variantes en Bsale por SKU.
     */
    public function searchVariant(): \CodeIgniter\HTTP\ResponseInterface
    {
        $sku = trim((string) $this->request->getGet('sku'));

        if (empty($sku)) {
            return $this->response->setJSON([]);
        }

        try {
            $adapter  = new BsaleAdapter($this->config->accessToken);
            $variants = $adapter->findVariantBySku($sku);
        } catch (\Throwable $e) {
            log_message('error', '[Bsale::searchVariant] ' . $e->getMessage());
            $variants = [];
        }

        return $this->response->setJSON($variants);
    }
}
