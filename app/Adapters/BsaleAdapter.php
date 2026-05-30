<?php

namespace App\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Cliente HTTP puro de la API de Bsale.
 * No contiene lógica de negocio — solo serializa requests y deserializa respuestas.
 */
class BsaleAdapter
{
    private Client $client;

    public function __construct(string $accessToken, ?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.bsale.cl/v1/',
            'headers'  => [
                'access_token' => $accessToken,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout'  => 30,
        ]);
    }

    /**
     * Crea un documento en Bsale (boleta, nota de despacho, etc.).
     *
     * @param  array $payload  Payload serializado desde BsaleDocumentDTO::toArray()
     * @return array{id: int, urlPdf: string}
     * @throws \RuntimeException
     */
    public function createDocument(array $payload): array
    {
        try {
            $response = $this->client->post('documents.json', [
                'json' => $payload,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return [
                'id'       => (int) ($data['id'] ?? 0),
                'urlPdf'   => (string) ($data['urlPdf'] ?? ''),
                'response' => $data ?? [],
            ];
        } catch (GuzzleException $e) {
            $statusCode = $e->getCode();
            $message    = match ($statusCode) {
                400     => 'Payload inválido (400): verifique los campos del documento.',
                401     => 'Token de acceso incorrecto (401): revise BSALE_ACCESS_TOKEN.',
                404     => 'Variante no encontrada (404): verifique que bsale_variant_id existe.',
                default => "Error en API de Bsale ({$statusCode}): " . $e->getMessage(),
            };
            throw new \RuntimeException($message, $statusCode, $e);
        }
    }

    /**
     * Busca variantes en Bsale por SKU.
     * Útil para poblar bsale_variant_map desde la UI.
     *
     * @return array[]  Lista de variantes [{id, sku, product_name}]
     */
    public function findVariantBySku(string $sku): array
    {
        try {
            $response = $this->client->get('variants.json', [
                'query' => ['code' => $sku, 'state' => 1],
            ]);

            $data  = json_decode((string) $response->getBody(), true);
            $items = $data['items'] ?? [];

            return array_map(fn(array $v) => [
                'id'           => (int) $v['id'],
                'sku'          => (string) ($v['code'] ?? ''),
                'product_name' => (string) ($v['product']['name'] ?? ''),
            ], $items);
        } catch (GuzzleException $e) {
            log_message('error', '[BsaleAdapter] findVariantBySku error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retorna el listado de tipos de documentos disponibles en la cuenta.
     * Útil para la configuración inicial (saber qué document_type_id usar).
     */
    public function getDocumentTypes(): array
    {
        try {
            $response = $this->client->get('document_types.json');
            $data     = json_decode((string) $response->getBody(), true);
            return $data['items'] ?? [];
        } catch (GuzzleException $e) {
            log_message('error', '[BsaleAdapter] getDocumentTypes error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retorna el listado de sucursales de la cuenta.
     */
    public function getOffices(): array
    {
        try {
            $response = $this->client->get('offices.json');
            $data     = json_decode((string) $response->getBody(), true);
            return $data['items'] ?? [];
        } catch (GuzzleException $e) {
            log_message('error', '[BsaleAdapter] getOffices error: ' . $e->getMessage());
            return [];
        }
    }
}
