<?php

namespace App\DTOs;

class BsaleDocumentDetailDTO
{
    public function __construct(
        public readonly int    $variantId,
        public readonly int    $quantity,
        public readonly ?float $unitValue = null, // null = Bsale usa el precio de la lista de precios
    ) {}

    public function toArray(): array
    {
        $data = [
            'variantId' => $this->variantId,
            'quantity'  => $this->quantity,
        ];

        if ($this->unitValue !== null) {
            $data['unitValue'] = $this->unitValue;
        }

        return $data;
    }
}
