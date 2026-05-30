<?php

namespace App\Services;

/**
 * Responsabilidad única: validar y parsear webhooks entrantes de WooCommerce.
 * No toca la API de Bsale.
 */
class BsaleWebhookService
{
    /**
     * Verifica la firma HMAC-SHA256 del webhook de WooCommerce.
     * WooCommerce firma el raw body con el secret configurado en su panel.
     *
     * Header: X-WC-Webhook-Signature
     * Algoritmo: base64(HMAC-SHA256(rawBody, secret))
     */
    public function validateSignature(string $rawBody, string $signature, string $secret): bool
    {
        if (empty($secret) || empty($signature)) {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        return hash_equals($expected, $signature);
    }

    /**
     * Extrae los datos relevantes del payload de WooCommerce para crear el documento en Bsale.
     *
     * @param  array $payload  Payload decodificado del webhook (order.created)
     * @return array{
     *   woo_order_id: int,
     *   client_email: string,
     *   items: array<array{woo_product_id: int, sku: string, quantity: int, unit_price: float}>
     * }
     * @throws \InvalidArgumentException  Si el payload no tiene la estructura esperada
     */
    public function parseOrderPayload(array $payload): array
    {
        $orderId = (int) ($payload['id'] ?? 0);
        if ($orderId === 0) {
            throw new \InvalidArgumentException('El payload no contiene un ID de pedido válido.');
        }

        $email = (string) ($payload['billing']['email'] ?? '');

        $lineItems = $payload['line_items'] ?? [];
        if (empty($lineItems)) {
            throw new \InvalidArgumentException("El pedido #{$orderId} no contiene líneas de detalle.");
        }

        $items = [];
        foreach ($lineItems as $item) {
            $qty   = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0.0);

            // WooCommerce puede enviar price=0 en order.created (aún no finalizado).
            // Fallback: line_total ÷ cantidad, que siempre está disponible.
            if ($price === 0.0 && $qty > 0) {
                $price = (float) ($item['total'] ?? 0.0) / $qty;
            }

            $items[] = [
                'woo_product_id' => (int) ($item['product_id'] ?? $item['variation_id'] ?? 0),
                'name'           => (string) ($item['name'] ?? ''),
                'sku'            => (string) ($item['sku'] ?? ''),
                'quantity'       => $qty,
                'unit_price'     => $price,
            ];
        }

        return [
            'woo_order_id' => $orderId,
            'client_email' => $email,
            'items'        => $items,
        ];
    }
}
