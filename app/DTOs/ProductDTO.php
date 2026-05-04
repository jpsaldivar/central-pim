<?php

namespace App\DTOs;

/**
 * Normalized product representation that decouples platform-specific formats.
 * Acts as the canonical product schema for the ETL pipeline.
 */
class ProductDTO
{
    public int    $sourceId = 0;   // ID del producto en la plataforma de origen (Jumpseller)
    public string $sku = '';
    public string $name = '';
    public string $description = '';
    public string $brand = '';
    public int    $wooCommerceBrandId = 0;

    /** @var int[] IDs de categorías en WooCommerce resueltos durante la migración */
    public array $wooCategoryIds = [];
    public string $regularPrice = '0';
    public string $salePrice = '';
    public int $stockQuantity = 0;
    public bool $manageStock = true;
    public string $status = 'publish'; // publish | draft
    public string $type = 'simple';   // simple | variable

    /** @var array<array{name: string}> */
    public array $categories = [];

    /** @var array<array{src: string}> */
    public array $images = [];

    /** @var array<array{name: string, options: string[], variation: bool, visible: bool}> */
    public array $attributes = [];

    /** @var VariantDTO[] */
    public array $variants = [];

    /**
     * Build a ProductDTO from a Jumpseller product payload.
     */
    public static function fromJumpseller(array $product): self
    {
        $dto = new self();
        $dto->sourceId = (int)($product['id'] ?? 0);
        $dto->sku = (string)($product['sku'] ?? '');
        $dto->name = (string)($product['name'] ?? '');
        $dto->description = strip_tags((string)($product['description'] ?? ''));
        $dto->regularPrice = (string)($product['price'] ?? '0');
        $dto->salePrice = isset($product['sale_price']) && $product['sale_price'] > 0
            ? (string)$product['sale_price']
            : '';
        $dto->brand = trim((string)($product['brand'] ?? ''));

        // Jumpseller represents unlimited/infinite stock in two ways:
        //   a) stock_management = false  → no stock tracking
        //   b) stock = null              → no quantity limit
        // In both cases we mark the product as unmanaged (no stock limit).
        $stockValue      = $product['stock'] ?? null;
        $stockManagement = $product['stock_management'] ?? true;
        $dto->manageStock   = (bool)$stockManagement && $stockValue !== null;
        $dto->stockQuantity = $dto->manageStock ? (int)$stockValue : 0;
        $dto->status = ($product['status'] ?? 'available') === 'available' ? 'publish' : 'draft';

        foreach ($product['categories'] ?? [] as $cat) {
            if (!empty($cat['name'])) {
                $dto->categories[] = ['name' => $cat['name']];
            }
        }

        foreach ($product['images'] ?? [] as $img) {
            if (!empty($img['url'])) {
                $dto->images[] = ['src' => $img['url']];
            }
        }

        // Determine if product has meaningful variants (more than the default single variant)
        $variants = $product['variants'] ?? [];
        $hasVariants = count($variants) > 1
            || (count($variants) === 1 && !empty($variants[0]['options']));

        if ($hasVariants) {
            $dto->type = 'variable';
            $attributeMap = [];

            foreach ($variants as $variant) {
                $dto->variants[] = VariantDTO::fromJumpseller($variant, $dto->manageStock);
                foreach ($variant['options'] ?? [] as $option) {
                    $name = $option['name'] ?? '';
                    $value = $option['value'] ?? '';
                    if ($name && $value) {
                        $attributeMap[$name][] = $value;
                    }
                }
            }

            foreach ($attributeMap as $attrName => $attrValues) {
                $dto->attributes[] = [
                    'name'      => $attrName,
                    'options'   => array_values(array_unique($attrValues)),
                    'variation' => true,
                    'visible'   => true,
                ];
            }
        }

        return $dto;
    }

    /**
     * Convert to WooCommerce product payload.
     * Variable products omit price/stock (set at variation level).
     */
    public function toWooCommerce(): array
    {
        // Combinar categorías por nombre (Jumpseller) con IDs resueltos en WooCommerce
        $cats = $this->categories;
        foreach ($this->wooCategoryIds as $catId) {
            $cats[] = ['id' => $catId];
        }

        $data = [
            'name'        => $this->name,
            'type'        => $this->type,
            'sku'         => $this->sku,
            'status'      => $this->status,
            'description' => $this->description,
            'categories'  => $cats,
            'images'      => $this->images,
        ];

        if ($this->type === 'simple') {
            $data['regular_price'] = $this->regularPrice;
            $data['manage_stock']  = $this->manageStock;
            if ($this->manageStock) {
                $data['stock_quantity'] = $this->stockQuantity;
            } else {
                $data['stock_status'] = 'instock';
            }
            if ($this->salePrice !== '') {
                $data['sale_price'] = $this->salePrice;
            }
        } else {
            $data['attributes'] = $this->attributes;
        }

        if ($this->wooCommerceBrandId > 0) {
            $data['brands'] = [$this->wooCommerceBrandId];
        }

        return $data;
    }
}
