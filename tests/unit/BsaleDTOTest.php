<?php

use App\DTOs\BsaleDocumentDetailDTO;
use App\DTOs\BsaleDocumentDTO;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class BsaleDTOTest extends CIUnitTestCase
{
    // -------------------------------------------------------------------------
    // BsaleDocumentDetailDTO
    // -------------------------------------------------------------------------

    public function testDetailToArrayProducesExpectedKeys(): void
    {
        $detail = new BsaleDocumentDetailDTO(
            variantId: 101,
            quantity:  3,
            unitValue: 15000.0,
        );

        $result = $detail->toArray();

        $this->assertSame(['variantId', 'quantity', 'unitValue'], array_keys($result));
        $this->assertSame(101, $result['variantId']);
        $this->assertSame(3, $result['quantity']);
        $this->assertSame(15000.0, $result['unitValue']);
    }

    // -------------------------------------------------------------------------
    // BsaleDocumentDTO
    // -------------------------------------------------------------------------

    public function testDocumentToArrayProducesExpectedStructure(): void
    {
        $detail = new BsaleDocumentDetailDTO(variantId: 55, quantity: 2, unitValue: 10000.0);

        $dto = new BsaleDocumentDTO(
            documentTypeId: 8,
            officeId:       1,
            priceListId:    1,
            clientEmail:    'cliente@ejemplo.cl',
            details:        [$detail],
            note:           'Pedido WooCommerce #42',
        );

        $before = time();
        $result = $dto->toArray();
        $after  = time();

        // Claves obligatorias presentes
        foreach (['documentTypeId', 'officeId', 'priceListId', 'emissionDate', 'declare', 'note', 'client', 'details'] as $key) {
            $this->assertArrayHasKey($key, $result, "Falta la clave '{$key}' en el payload");
        }

        $this->assertSame(8, $result['documentTypeId']);
        $this->assertSame(1, $result['officeId']);
        $this->assertSame(1, $result['priceListId']);
        $this->assertSame(1, $result['declare']);
        $this->assertSame('Pedido WooCommerce #42', $result['note']);
        $this->assertSame(['email' => 'cliente@ejemplo.cl'], $result['client']);

        // emissionDate debe ser un timestamp Unix dentro del rango de la ejecución
        $this->assertGreaterThanOrEqual($before, $result['emissionDate']);
        $this->assertLessThanOrEqual($after, $result['emissionDate']);
    }

    public function testDocumentToArraySerializesDetails(): void
    {
        $details = [
            new BsaleDocumentDetailDTO(variantId: 10, quantity: 1, unitValue: 5000.0),
            new BsaleDocumentDetailDTO(variantId: 20, quantity: 4, unitValue: 2500.0),
        ];

        $dto = new BsaleDocumentDTO(
            documentTypeId: 8,
            officeId:       1,
            priceListId:    1,
            clientEmail:    'a@b.cl',
            details:        $details,
        );

        $result = $dto->toArray();

        $this->assertCount(2, $result['details']);
        $this->assertSame(['variantId' => 10, 'quantity' => 1, 'unitValue' => 5000.0], $result['details'][0]);
        $this->assertSame(['variantId' => 20, 'quantity' => 4, 'unitValue' => 2500.0], $result['details'][1]);
    }

    public function testNoteDefaultsToEmptyString(): void
    {
        $dto = new BsaleDocumentDTO(
            documentTypeId: 8,
            officeId:       1,
            priceListId:    1,
            clientEmail:    'a@b.cl',
            details:        [],
        );

        $this->assertSame('', $dto->toArray()['note']);
    }
}
