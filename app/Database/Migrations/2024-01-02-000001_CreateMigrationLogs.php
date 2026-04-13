<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMigrationLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Ej: jumpseller_to_woo',
            ],
            'sku' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => '',
            ],
            'nombre_producto' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'default'    => '',
            ],
            'accion' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'create, update, skip, upsert, migration_start, migration_end, page_processed',
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'comment'    => 'success, error, warning, info',
            ],
            'mensaje' => [
                'type' => 'TEXT',
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['tipo', 'estado']);
        $this->forge->addKey('sku');
        $this->forge->addKey('created_at');

        $this->forge->createTable('migration_logs');
    }

    public function down(): void
    {
        $this->forge->dropTable('migration_logs');
    }
}
