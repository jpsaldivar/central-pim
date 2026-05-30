<?php

namespace App\DTOs;

class BsaleDocumentDetailDTO
{
    public function __construct(
        public readonly int   $variantId,  // bsale_variant_map.bsale_variant_id
        public readonly int   $quantity,
        public readonly float $unitValue,  // Precio unitario (sin IVA si el tipo de doc incluye IVA)
    ) {}

    public function toArray(): array
    {
        return [
            'variantId' => $this->variantId,
            'quantity'  => $this->quantity,
            'unitValue' => $this->unitValue,
        ];
    }
}
