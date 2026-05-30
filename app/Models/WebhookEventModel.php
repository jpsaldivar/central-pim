<?php

namespace App\Models;

use CodeIgniter\Model;

class WebhookEventModel extends Model
{
    protected $table      = 'webhook_events';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'fuente',
        'topic',
        'payload',
        'firma_valida',
        'procesado',
        'documento_id',
        'created_at',
    ];

    protected $useTimestamps = false;

    // -------------------------------------------------------------------------

    /**
     * Registra un evento entrante. Siempre se llama antes de validar la firma.
     */
    public function registrar(string $fuente, string $topic, string $rawPayload, bool $firmaValida = false): int
    {
        $this->insert([
            'fuente'      => $fuente,
            'topic'       => $topic,
            'payload'     => $rawPayload,
            'firma_valida' => $firmaValida ? 1 : 0,
            'procesado'   => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->getInsertID();
    }

    /**
     * Marca el evento como procesado y lo vincula al documento generado.
     */
    public function marcarProcesado(int $id, int $documentoId): void
    {
        $this->update($id, [
            'procesado'    => 1,
            'documento_id' => $documentoId,
        ]);
    }
}
