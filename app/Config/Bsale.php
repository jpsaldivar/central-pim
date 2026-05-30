<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Bsale extends BaseConfig
{
    /** Token de acceso a la API de Bsale */
    public string $accessToken = '';

    /** ID de la sucursal en Bsale desde la cual se emiten los documentos */
    public int $officeId = 0;

    /** ID del tipo de documento para boleta electrónica */
    public int $boletaTypeId = 0;

    /** ID del tipo de documento para factura electrónica */
    public int $facturaTypeId = 0;

    /** ID de la lista de precio en Bsale a usar en cada línea */
    public int $priceListId = 1;

    /**
     * Secret del webhook configurado en WooCommerce.
     * WooCommerce usa esto para firmar el payload con HMAC-SHA256.
     */
    public string $webhookSecret = '';

    public function __construct()
    {
        parent::__construct();
        $this->accessToken   = env('BSALE_ACCESS_TOKEN', '');
        $this->officeId      = (int) env('BSALE_OFFICE_ID', 0);
        $this->boletaTypeId  = (int) env('BSALE_BOLETA_TYPE_ID', 0);
        $this->facturaTypeId = (int) env('BSALE_FACTURA_TYPE_ID', 0);
        $this->priceListId   = (int) env('BSALE_PRICE_LIST_ID', 1);
        $this->webhookSecret = env('BSALE_WEBHOOK_SECRET', '');
    }
}
