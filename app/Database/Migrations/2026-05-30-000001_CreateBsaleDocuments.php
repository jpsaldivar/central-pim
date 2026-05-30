<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBsaleDocuments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'woo_order_id'       => ['type' => 'INT', 'unsigned' => true],
            'bsale_document_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'bsale_document_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'default' => null],
            'estado'             => ['type' => 'ENUM', 'constraint' => ['pendiente', 'creado', 'error'], 'default' => 'pendiente'],
            'mensaje_error'      => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'payload_recibido'   => ['type' => 'JSON'],
            'payload_enviado'    => ['type' => 'JSON', 'null' => true, 'default' => null],
            'created_at'         => ['type' => 'DATETIME'],
            'updated_at'         => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('woo_order_id');
        $this->forge->createTable('bsale_documents');
    }

    public function down(): void
    {
        $this->forge->dropTable('bsale_documents', true);
    }
}
