<?php

use App\Services\BsaleWebhookService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BsaleWebhookServiceTest extends CIUnitTestCase
{
    private BsaleWebhookService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new BsaleWebhookService();
    }

    // -------------------------------------------------------------------------
    // validateSignature
    // -------------------------------------------------------------------------

    public function testValidSignatureReturnsTrueWithKnownVector(): void
    {
        $secret  = 'mi_webhook_secret';
        $body    = '{"id":42,"status":"processing"}';
        $sig     = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $this->assertTrue($this->svc->validateSignature($body, $sig, $secret));
    }

    public function testTamperedBodyFailsValidation(): void
    {
        $secret  = 'mi_webhook_secret';
        $body    = '{"id":42,"status":"processing"}';
        $sig     = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $this->assertFalse($this->svc->validateSignature($body . 'X', $sig, $secret));
    }

    public function testWrongSecretFailsValidation(): void
    {
        $body = '{"id":42}';
        $sig  = base64_encode(hash_hmac('sha256', $body, 'secreto_correcto', true));

        $this->assertFalse($this->svc->validateSignature($body, $sig, 'secreto_incorrecto'));
    }

    public function testEmptySecretAlwaysReturnsFalse(): void
    {
        $body = '{"id":42}';
        $sig  = base64_encode(hash_hmac('sha256', $body, '', true));

        $this->assertFalse($this->svc->validateSignature($body, $sig, ''));
    }

    public function testEmptySignatureAlwaysReturnsFalse(): void
    {
        $this->assertFalse($this->svc->validateSignature('body', '', 'secret'));
    }

    // -------------------------------------------------------------------------
    // parseOrderPayload
    // -------------------------------------------------------------------------

    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'id'         => 42,
            'billing'    => ['email' => 'cliente@ejemplo.cl'],
            'line_items' => [
                [
                    'product_id' => 100,
                    'sku'        => 'CAM-001',
                    'quantity'   => 2,
                    'price'      => '15000',
                ],
            ],
        ], $overrides);
    }

    public function testParseOrderPayloadExtractsOrderId(): void
    {
        $result = $this->svc->parseOrderPayload($this->makePayload());
        $this->assertSame(42, $result['woo_order_id']);
    }

    public function testParseOrderPayloadExtractsClientEmail(): void
    {
        $result = $this->svc->parseOrderPayload($this->makePayload());
        $this->assertSame('cliente@ejemplo.cl', $result['client_email']);
    }

    public function testParseOrderPayloadExtractsLineItems(): void
    {
        $result = $this->svc->parseOrderPayload($this->makePayload());

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];

        $this->assertSame(100, $item['woo_product_id']);
        $this->assertSame('CAM-001', $item['sku']);
        $this->assertSame(2, $item['quantity']);
        $this->assertSame(15000.0, $item['unit_price']);
    }

    public function testParseOrderPayloadThrowsWhenNoOrderId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->parseOrderPayload(['billing' => ['email' => 'a@b.cl'], 'line_items' => [['product_id' => 1]]]);
    }

    public function testParseOrderPayloadThrowsWhenNoLineItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->parseOrderPayload(['id' => 1, 'billing' => ['email' => 'a@b.cl'], 'line_items' => []]);
    }

    public function testParseOrderPayloadHandlesMultipleItems(): void
    {
        $payload = $this->makePayload([
            'line_items' => [
                ['product_id' => 10, 'sku' => 'A', 'quantity' => 1, 'price' => '5000'],
                ['product_id' => 20, 'sku' => 'B', 'quantity' => 3, 'price' => '2000'],
            ],
        ]);

        $result = $this->svc->parseOrderPayload($payload);

        $this->assertCount(2, $result['items']);
        $this->assertSame(10, $result['items'][0]['woo_product_id']);
        $this->assertSame(20, $result['items'][1]['woo_product_id']);
    }
}
