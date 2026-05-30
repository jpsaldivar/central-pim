<?php

use App\Adapters\BsaleAdapter;
use App\Models\BsaleDocumentModel;
use App\Models\BsaleVariantMapModel;
use App\Services\BsaleDocumentService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Bsale as BsaleConfig;

/**
 * @internal
 */
final class BsaleDocumentServiceTest extends CIUnitTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeConfig(): BsaleConfig
    {
        $config               = new BsaleConfig();
        $config->boletaTypeId  = 8;
        $config->facturaTypeId = 9;
        $config->officeId      = 1;
        $config->priceListId   = 1;
        return $config;
    }

    private function makeParsedOrder(array $overrides = []): array
    {
        return array_merge([
            'woo_order_id' => 42,
            'client_email' => 'cliente@ejemplo.cl',
            'items'        => [
                ['woo_product_id' => 100, 'sku' => 'CAM-001', 'quantity' => 2, 'unit_price' => 15000.0],
            ],
        ], $overrides);
    }

    private function makeService(
        BsaleAdapter         $adapter,
        BsaleVariantMapModel $variantMap,
        BsaleDocumentModel   $documentModel,
    ): BsaleDocumentService {
        return new BsaleDocumentService($adapter, $variantMap, $documentModel, $this->makeConfig());
    }

    // -------------------------------------------------------------------------
    // processOrder — solo registra, no toca Bsale
    // -------------------------------------------------------------------------

    public function testProcessOrderRegistersOrderAsPendiente(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('existePorOrden')->willReturn(false);
        $documentModel->method('crear')->willReturn(7);

        // No debe llamar al adapter en ningún caso
        $adapter->expects($this->never())->method('createDocument');

        $svc   = $this->makeService($adapter, $variantMap, $documentModel);
        $docId = $svc->processOrder($this->makeParsedOrder());

        $this->assertSame(7, $docId);
    }

    public function testProcessOrderIsIdempotentForSameOrder(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('existePorOrden')->willReturn(true);
        $documentModel->method('buscarPorOrden')->willReturn(['id' => 3]);
        $documentModel->expects($this->never())->method('crear');
        $adapter->expects($this->never())->method('createDocument');

        $svc   = $this->makeService($adapter, $variantMap, $documentModel);
        $docId = $svc->processOrder($this->makeParsedOrder());

        $this->assertSame(3, $docId);
    }

    // -------------------------------------------------------------------------
    // emitirDocumento — flujo exitoso
    // -------------------------------------------------------------------------

    public function testEmitirDocumentoCreatesDocumentInBsale(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('find')->willReturn([
            'id'               => 7,
            'estado'           => 'pendiente',
            'payload_recibido' => json_encode($this->makeParsedOrder()),
        ]);
        $documentModel->expects($this->once())->method('guardarPayloadEnviado');
        $documentModel->expects($this->once())->method('marcarCreado')
            ->with(7, 999, 'https://bsale.cl/docs/999.pdf', $this->callback(fn($v) => is_array($v)));

        $variantMap->method('findByWooProductId')->willReturn(['bsale_variant_id' => 55]);

        $adapter->method('createDocument')->willReturn([
            'id'       => 999,
            'urlPdf'   => 'https://bsale.cl/docs/999.pdf',
            'response' => ['id' => 999],
        ]);

        $svc = $this->makeService($adapter, $variantMap, $documentModel);
        $svc->emitirDocumento(7, 8); // 8 = boleta
    }

    public function testEmitirDocumentoResetsErrorStateBeforeRetry(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('find')->willReturn([
            'id'               => 5,
            'estado'           => 'error',
            'payload_recibido' => json_encode($this->makeParsedOrder()),
        ]);

        // Debe resetear a pendiente antes de intentar
        $documentModel->expects($this->once())->method('update')
            ->with(5, $this->callback(fn($d) => $d['estado'] === 'pendiente'));

        $variantMap->method('findByWooProductId')->willReturn(['bsale_variant_id' => 55]);
        $adapter->method('createDocument')->willReturn(['id' => 1, 'urlPdf' => '', 'response' => []]);

        $svc = $this->makeService($adapter, $variantMap, $documentModel);
        $svc->emitirDocumento(5, 8);
    }

    // -------------------------------------------------------------------------
    // emitirDocumento — ítem sin mapeo
    // -------------------------------------------------------------------------

    public function testEmitirDocumentoMarksErrorWhenItemHasNoMapping(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('find')->willReturn([
            'id'               => 5,
            'estado'           => 'pendiente',
            'payload_recibido' => json_encode($this->makeParsedOrder()),
        ]);
        $documentModel->expects($this->once())->method('marcarError')
            ->with(5, $this->stringContains('CAM-001'));

        $variantMap->method('findByWooProductId')->willReturn(null);
        $adapter->expects($this->never())->method('createDocument');

        $svc = $this->makeService($adapter, $variantMap, $documentModel);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CAM-001/');
        $svc->emitirDocumento(5, 8);
    }

    // -------------------------------------------------------------------------
    // emitirDocumento — error en API de Bsale
    // -------------------------------------------------------------------------

    public function testEmitirDocumentoMarksErrorWhenApiFails(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('find')->willReturn([
            'id'               => 6,
            'estado'           => 'pendiente',
            'payload_recibido' => json_encode($this->makeParsedOrder()),
        ]);
        $documentModel->expects($this->once())->method('marcarError')
            ->with(6, $this->stringContains('Token de acceso incorrecto'));

        $variantMap->method('findByWooProductId')->willReturn(['bsale_variant_id' => 55]);
        $adapter->method('createDocument')
            ->willThrowException(new \RuntimeException('Token de acceso incorrecto (401)'));

        $svc = $this->makeService($adapter, $variantMap, $documentModel);

        $this->expectException(\RuntimeException::class);
        $svc->emitirDocumento(6, 8);
    }

    // -------------------------------------------------------------------------
    // emitirDocumento — usa el documentTypeId recibido como parámetro
    // -------------------------------------------------------------------------

    public function testEmitirDocumentoUsesProvidedDocumentTypeId(): void
    {
        $documentModel = $this->createMock(BsaleDocumentModel::class);
        $variantMap    = $this->createMock(BsaleVariantMapModel::class);
        $adapter       = $this->createMock(BsaleAdapter::class);

        $documentModel->method('find')->willReturn([
            'id'               => 7,
            'estado'           => 'pendiente',
            'payload_recibido' => json_encode($this->makeParsedOrder()),
        ]);

        $variantMap->method('findByWooProductId')->willReturn(['bsale_variant_id' => 55]);

        // Verificar que el payload enviado usa el documentTypeId correcto (9 = factura)
        $adapter->expects($this->once())
            ->method('createDocument')
            ->with($this->callback(fn($p) => $p['documentTypeId'] === 9))
            ->willReturn(['id' => 1, 'urlPdf' => '', 'response' => []]);

        $svc = $this->makeService($adapter, $variantMap, $documentModel);
        $svc->emitirDocumento(7, 9); // 9 = factura
    }
}
