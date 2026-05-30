<?php

namespace App\DTOs;

class BsaleDocumentDTO
{
    /**
     * @param BsaleDocumentDetailDTO[] $details
     */
    public function __construct(
        public readonly int    $documentTypeId,
        public readonly int    $officeId,
        public readonly int    $priceListId,
        public readonly string $clientEmail,  // Email del comprador (para boleta electrónica)
        public readonly array  $details,      // BsaleDocumentDetailDTO[]
        public readonly string $note = '',    // Referencia: número de pedido WooCommerce
    ) {}

    /**
     * Serializa al formato exacto que espera POST /documents.json de Bsale.
     */
    public function toArray(): array
    {
        return [
            'documentTypeId' => $this->documentTypeId,
            'officeId'       => $this->officeId,
            'priceListId'    => $this->priceListId,
            'emissionDate'   => time(),
            'declare'        => 1,
            'note'           => $this->note,
            'client'         => ['email' => $this->clientEmail],
            'details'        => array_map(
                fn(BsaleDocumentDetailDTO $d) => $d->toArray(),
                $this->details
            ),
        ];
    }
}
