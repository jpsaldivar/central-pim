<?php

namespace App\Services;

use App\Adapters\BsaleAdapter;
use App\DTOs\BsaleDocumentDTO;
use App\DTOs\BsaleDocumentDetailDTO;
use App\Models\BsaleDocumentModel;
use App\Models\BsaleVariantMapModel;
use Config\Bsale as BsaleConfig;

/**
 * Orquestador principal del flujo WooCommerce → Bsale.
 */
class BsaleDocumentService
{
    public function __construct(
        private BsaleAdapter         $adapter,
        private BsaleVariantMapModel $variantMap,
        private BsaleDocumentModel   $documentModel,
        private BsaleConfig          $config,
    ) {}

    /**
     * Registra un pedido de WooCommerce como pendiente, sin tocar Bsale.
     * La emisión del documento (boleta o factura) se hace manualmente desde la UI.
     *
     * @param  array $parsedOrder  Output de BsaleWebhookService::parseOrderPayload()
     * @return int   ID del registro en bsale_documents
     */
    public function processOrder(array $parsedOrder): int
    {
        $wooOrderId = $parsedOrder['woo_order_id'];

        // Idempotencia: no duplicar si ya existe un registro para este pedido
        if ($this->documentModel->existePorOrden($wooOrderId)) {
            $existing = $this->documentModel->buscarPorOrden($wooOrderId);
            log_message('info', "[BsaleDocumentService] Pedido #{$wooOrderId} ya registrado (doc #{$existing['id']}).");
            return (int) $existing['id'];
        }

        return $this->documentModel->crear($wooOrderId, $parsedOrder);
    }

    /**
     * Emite el documento en Bsale para un registro existente.
     * Se llama manualmente desde la UI eligiendo boleta o factura.
     *
     * @param  int $docId          ID del registro en bsale_documents
     * @param  int $documentTypeId ID del tipo de documento en Bsale (boleta o factura)
     * @throws \RuntimeException   Si hay ítems sin mapeo o falla la API de Bsale
     */
    public function emitirDocumento(int $docId, int $documentTypeId): void
    {
        $documento = $this->documentModel->find($docId);
        if ($documento === null) {
            throw new \RuntimeException("Documento #{$docId} no encontrado.");
        }

        $parsedOrder = json_decode($documento['payload_recibido'], true) ?? [];
        $items       = $parsedOrder['items']        ?? [];
        $clientEmail = $parsedOrder['client_email'] ?? '';
        $wooOrderId  = $parsedOrder['woo_order_id'] ?? 0;

        // Si venía de un error anterior, limpiar antes de reintentar
        if ($documento['estado'] === 'error') {
            $this->documentModel->update($docId, [
                'estado'        => 'pendiente',
                'mensaje_error' => null,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }

        try {
            // Mapear cada ítem a bsale_variant_id
            $details  = [];
            $sinMapeo = [];

            foreach ($items as $item) {
                $map = $this->variantMap->findByWooProductId($item['woo_product_id']);

                if ($map === null) {
                    $sinMapeo[] = $item['sku'] ?: "woo_product_id:{$item['woo_product_id']}";
                    continue;
                }

                $details[] = new BsaleDocumentDetailDTO(
                    variantId: (int) $map['bsale_variant_id'],
                    quantity:  $item['quantity'],
                    unitValue: $item['unit_price'],
                );
            }

            if (!empty($sinMapeo)) {
                throw new \RuntimeException(
                    'Ítems sin mapeo en bsale_variant_map: ' . implode(', ', $sinMapeo) . '. Agregar desde Bsale → Mapeo de Variantes.'
                );
            }

            // Construir DTO con el tipo elegido por el operador
            $dto = new BsaleDocumentDTO(
                documentTypeId: $documentTypeId,
                officeId:       $this->config->officeId,
                priceListId:    $this->config->priceListId,
                clientEmail:    $clientEmail,
                details:        $details,
                note:           "Pedido WooCommerce #{$wooOrderId}",
            );

            $payload = $dto->toArray();
            $this->documentModel->guardarPayloadEnviado($docId, $payload);

            $result = $this->adapter->createDocument($payload);

            $this->documentModel->marcarCreado($docId, $result['id'], $result['urlPdf'], $result['response'] ?? []);
            log_message('info', "[BsaleDocumentService] Documento Bsale #{$result['id']} emitido para pedido WC #{$wooOrderId}.");
        } catch (\RuntimeException $e) {
            $this->documentModel->marcarError($docId, $e->getMessage());
            log_message('error', "[BsaleDocumentService] Error al emitir doc #{$docId}: " . $e->getMessage());
            throw $e;
        }
    }
}
