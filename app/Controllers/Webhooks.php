<?php

namespace App\Controllers;

use App\Adapters\BsaleAdapter;
use App\Models\BsaleDocumentModel;
use App\Models\BsaleVariantMapModel;
use App\Models\WebhookEventModel;
use App\Services\BsaleDocumentService;
use App\Services\BsaleWebhookService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Bsale as BsaleConfig;

/**
 * Endpoint público para recibir webhooks de WooCommerce.
 * No requiere sesión — protegido por firma HMAC-SHA256.
 */
class Webhooks extends BaseController
{
    public function woocommerceOrderCreated(): ResponseInterface
    {
        $rawBody   = $this->request->getBody();
        $signature = $this->request->getHeaderLine('X-WC-Webhook-Signature');
        $topic     = $this->request->getHeaderLine('X-WC-Webhook-Topic') ?: 'order.created';

        $webhookModel = new WebhookEventModel();
        $bsaleConfig  = new BsaleConfig();
        $webhookSvc   = new BsaleWebhookService();

        // 1. Registrar el evento crudo siempre, antes de validar
        $eventId = $webhookModel->registrar('woocommerce', $topic, $rawBody ?: '');

        // 2. Validar firma
        $firmaValida = $webhookSvc->validateSignature($rawBody ?: '', $signature, $bsaleConfig->webhookSecret);

        if (!$firmaValida) {
            log_message('warning', '[Webhooks] Firma HMAC inválida recibida. Topic: ' . $topic);
            // Actualizamos el evento para reflejar que la firma fue inválida
            $webhookModel->update($eventId, ['firma_valida' => 0]);
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
        }

        $webhookModel->update($eventId, ['firma_valida' => 1]);

        // 3. Parsear payload
        $payload = json_decode($rawBody ?: '{}', true) ?? [];

        try {
            $parsedOrder = $webhookSvc->parseOrderPayload($payload);
        } catch (\InvalidArgumentException $e) {
            log_message('error', '[Webhooks] parseOrderPayload falló: ' . $e->getMessage());
            // Respondemos 200 para evitar reintentos de WooCommerce
            return $this->response->setStatusCode(200)->setJSON(['ok' => false]);
        }

        // 4. Procesar con BsaleDocumentService
        try {
            $documentService = new BsaleDocumentService(
                adapter:       new BsaleAdapter($bsaleConfig->accessToken),
                variantMap:    new BsaleVariantMapModel(),
                documentModel: new BsaleDocumentModel(),
                config:        $bsaleConfig,
            );

            $docId = $documentService->processOrder($parsedOrder);
            $webhookModel->marcarProcesado($eventId, $docId);
        } catch (\RuntimeException $e) {
            log_message('error', '[Webhooks] processOrder falló: ' . $e->getMessage());
            // Siempre 200 — el error queda en bsale_documents.estado='error'
        }

        // 5. Responder 200 (WooCommerce reintenta si no recibe 2xx en < 5s)
        return $this->response->setStatusCode(200)->setJSON(['ok' => true]);
    }
}
