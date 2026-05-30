<?php

namespace App\Models;

use CodeIgniter\Model;

class BsaleVariantMapModel extends Model
{
    protected $table      = 'bsale_variant_map';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'woo_product_id',
        'sku',
        'bsale_variant_id',
        'bsale_product_name',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    // -------------------------------------------------------------------------

    /**
     * Busca el mapeo por woo_product_id. Retorna null si no existe o está inactivo.
     */
    public function findByWooProductId(int $wooProductId): ?array
    {
        return $this->where('woo_product_id', $wooProductId)
                    ->where('activo', 1)
                    ->first();
    }

    /**
     * Busca el mapeo por SKU. Retorna null si no existe o está inactivo.
     */
    public function findBySku(string $sku): ?array
    {
        return $this->where('sku', $sku)
                    ->where('activo', 1)
                    ->first();
    }

    /**
     * Inserta o actualiza un mapeo (upsert por woo_product_id).
     */
    public function upsert(int $wooProductId, string $sku, int $bsaleVariantId, string $nombre): void
    {
        $existing = $this->where('woo_product_id', $wooProductId)->first();
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            $this->update($existing['id'], [
                'sku'                => $sku,
                'bsale_variant_id'   => $bsaleVariantId,
                'bsale_product_name' => $nombre,
                'activo'             => 1,
                'updated_at'         => $now,
            ]);
        } else {
            $this->insert([
                'woo_product_id'     => $wooProductId,
                'sku'                => $sku,
                'bsale_variant_id'   => $bsaleVariantId,
                'bsale_product_name' => $nombre,
                'activo'             => 1,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }

    /**
     * Retorna todos los mapeos ordenados por SKU para la vista de administración.
     */
    public function getTodos(): array
    {
        return $this->orderBy('sku', 'ASC')->findAll();
    }
}
