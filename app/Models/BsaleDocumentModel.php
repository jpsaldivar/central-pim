<?php

namespace App\Models;

use CodeIgniter\Model;

class BsaleDocumentModel extends Model
{
    protected $table      = 'bsale_documents';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'woo_order_id',
        'bsale_document_id',
        'bsale_document_url',
        'bsale_response',
        'estado',
        'mensaje_error',
        'payload_recibido',
        'payload_enviado',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false; // Manejamos manualmente para usar DATETIME

    // -------------------------------------------------------------------------

    /**
     * Inserta un nuevo registro con estado 'pendiente'.
     * Retorna el ID insertado.
     */
    public function crear(int $wooOrderId, array $payloadRecibido): int
    {
        $now = date('Y-m-d H:i:s');
        $this->insert([
            'woo_order_id'     => $wooOrderId,
            'estado'           => 'pendiente',
            'payload_recibido' => json_encode($payloadRecibido),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        return (int) $this->getInsertID();
    }

    /**
     * Marca el documento como creado exitosamente en Bsale.
     */
    public function marcarCreado(int $id, int $bsaleDocumentId, string $url, array $response = []): void
    {
        $this->update($id, [
            'estado'             => 'creado',
            'bsale_document_id'  => $bsaleDocumentId,
            'bsale_document_url' => $url,
            'bsale_response'     => empty($response) ? null : json_encode($response),
            'mensaje_error'      => null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Marca el documento como fallido, guardando el mensaje de error.
     */
    public function marcarError(int $id, string $mensaje): void
    {
        $this->update($id, [
            'estado'        => 'error',
            'mensaje_error' => $mensaje,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Guarda el payload que se envió a Bsale (para auditoría).
     */
    public function guardarPayloadEnviado(int $id, array $payload): void
    {
        $this->update($id, [
            'payload_enviado' => json_encode($payload),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Verifica si ya existe un documento para el pedido dado (idempotencia).
     */
    public function existePorOrden(int $wooOrderId): bool
    {
        return $this->where('woo_order_id', $wooOrderId)->countAllResults() > 0;
    }

    /**
     * Retorna el registro del documento para el pedido dado, o null si no existe.
     */
    public function buscarPorOrden(int $wooOrderId): ?array
    {
        return $this->where('woo_order_id', $wooOrderId)->first();
    }

    /**
     * Retorna los documentos más recientes, ordenados por fecha de creación.
     */
    public function getRecientes(int $limit = 50): array
    {
        return $this->orderBy('created_at', 'DESC')->findAll($limit);
    }

    /**
     * Estadísticas rápidas para el dashboard.
     */
    public function getEstadisticas(): array
    {
        $hoy    = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('-7 days'));

        return [
            'hoy'             => $this->where('DATE(created_at)', $hoy)->countAllResults(),
            'semana'          => $this->where('created_at >=', $semana . ' 00:00:00')->countAllResults(),
            'errores_total'   => $this->where('estado', 'error')->countAllResults(),
            'creados_total'   => $this->where('estado', 'creado')->countAllResults(),
        ];
    }
}
