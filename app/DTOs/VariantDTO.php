<?php

namespace App\DTOs;

/**
 * Normalized representation of a product variant between platforms.
 */
class VariantDTO
{
    public string $sku = '';
    public string $regularPrice = '0';
    public string $salePrice = '';
    public int $stockQuantity = 0;
    public bool $manageStock = true;
    /** @var array<array{name: string, option: string}> */
    public array $attributes = [];

    public static function fromJumpseller(array $variant): self
    {
        $dto = new self();
        $dto->sku = (string)($variant['sku'] ?? '');
        $dto->regularPrice = (string)($variant['price'] ?? '0');
        $dto->salePrice = isset($variant['sale_price']) && $variant['sale_price'] > 0
            ? (string)$variant['sale_price']
            : '';
        $dto->stockQuantity = (int)($variant['stock'] ?? 0);
        $dto->manageStock = true;

        foreach ($variant['options'] ?? [] as $option) {
            $dto->attributes[] = [
                'name'   => $option['name'] ?? '',
                'option' => $option['value'] ?? '',
            ];
        }

        return $dto;
    }

    public function toWooCommerce(): array
    {
        $data = [
            'sku'            => $this->sku,
            'regular_price'  => $this->regularPrice,
            'manage_stock'   => $this->manageStock,
            'stock_quantity' => $this->stockQuantity,
            'attributes'     => $this->attributes,
        ];

        if ($this->salePrice !== '') {
            $data['sale_price'] = $this->salePrice;
        }

        return $data;
    }
}
