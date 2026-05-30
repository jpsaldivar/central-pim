<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWebhookEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'fuente'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'woocommerce'],
            'topic'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'payload'     => ['type' => 'JSON'],
            'firma_valida' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'procesado'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'documento_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('documento_id', 'bsale_documents', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('webhook_events');
    }

    public function down(): void
    {
        $this->forge->dropTable('webhook_events', true);
    }
}
