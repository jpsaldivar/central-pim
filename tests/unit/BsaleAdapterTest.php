<?php

use App\Adapters\BsaleAdapter;
use CodeIgniter\Test\CIUnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * @internal
 */
final class BsaleAdapterTest extends CIUnitTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdapter(array $responses): BsaleAdapter
    {
        $mock    = new MockHandler($responses);
        $client  = new Client(['handler' => HandlerStack::create($mock)]);
        return new BsaleAdapter('fake-token', $client);
    }

    private function makeErrorResponse(int $status): ClientException
    {
        $request  = new Request('POST', 'documents.json');
        $response = new Response($status, [], '{"error":"fail"}');
        return new ClientException('Error', $request, $response);
    }

    // -------------------------------------------------------------------------
    // createDocument
    // -------------------------------------------------------------------------

    public function testCreateDocumentReturnsIdAndUrl(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'id'     => 999,
                'urlPdf' => 'https://bsale.cl/docs/999.pdf',
            ])),
        ]);

        $result = $adapter->createDocument([
            'documentTypeId' => 8,
            'officeId'       => 1,
            'priceListId'    => 1,
            'emissionDate'   => time(),
            'declare'        => 1,
            'note'           => 'Pedido WooCommerce #42',
            'client'         => ['email' => 'test@example.com'],
            'details'        => [
                ['variantId' => 101, 'quantity' => 2, 'unitValue' => 10000.0],
            ],
        ]);

        $this->assertSame(999, $result['id']);
        $this->assertSame('https://bsale.cl/docs/999.pdf', $result['urlPdf']);
    }

    public function testCreateDocumentThrowsOn400WithDescriptiveMessage(): void
    {
        $adapter = $this->makeAdapter([
            $this->makeErrorResponse(400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Payload inválido \(400\)/');

        $adapter->createDocument(['documentTypeId' => 8]);
    }

    public function testCreateDocumentThrowsOn401WithDescriptiveMessage(): void
    {
        $adapter = $this->makeAdapter([
            $this->makeErrorResponse(401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Token de acceso incorrecto \(401\)/');

        $adapter->createDocument(['documentTypeId' => 8]);
    }

    public function testCreateDocumentThrowsOn404WithDescriptiveMessage(): void
    {
        $adapter = $this->makeAdapter([
            $this->makeErrorResponse(404),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Variante no encontrada \(404\)/');

        $adapter->createDocument(['documentTypeId' => 8]);
    }

    // -------------------------------------------------------------------------
    // findVariantBySku
    // -------------------------------------------------------------------------

    public function testFindVariantBySkuReturnsNormalizedArray(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'items' => [
                    [
                        'id'      => 55,
                        'code'    => 'CAM-001',
                        'product' => ['name' => 'Cámara Canon'],
                    ],
                ],
            ])),
        ]);

        $result = $adapter->findVariantBySku('CAM-001');

        $this->assertCount(1, $result);
        $this->assertSame(55, $result[0]['id']);
        $this->assertSame('CAM-001', $result[0]['sku']);
        $this->assertSame('Cámara Canon', $result[0]['product_name']);
    }

    public function testFindVariantBySkuReturnsEmptyArrayOnError(): void
    {
        $adapter = $this->makeAdapter([
            $this->makeErrorResponse(500),
        ]);

        $result = $adapter->findVariantBySku('NO-EXISTE');

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // getDocumentTypes / getOffices
    // -------------------------------------------------------------------------

    public function testGetDocumentTypesReturnsItems(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'items' => [
                    ['id' => 8, 'name' => 'Nota de despacho'],
                    ['id' => 9, 'name' => 'Boleta'],
                ],
            ])),
        ]);

        $result = $adapter->getDocumentTypes();

        $this->assertCount(2, $result);
        $this->assertSame(8, $result[0]['id']);
    }

    public function testGetOfficesReturnsItems(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode([
                'items' => [
                    ['id' => 1, 'name' => 'Casa Matriz'],
                ],
            ])),
        ]);

        $result = $adapter->getOffices();

        $this->assertCount(1, $result);
        $this->assertSame('Casa Matriz', $result[0]['name']);
    }
}
