<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScraperRuns extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tienda_id' => ['type' => 'INT', 'unsigned' => true],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['en_progreso', 'completado', 'error'],
                'default'    => 'en_progreso',
            ],
            'total_encontrados' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_nuevos'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_cambios'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'mensaje_error'     => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'iniciado_en'       => ['type' => 'DATETIME'],
            'finalizado_en'     => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('tienda_id');
        $this->forge->addForeignKey('tienda_id', 'tiendas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scraper_runs');
    }

    public function down()
    {
        $this->forge->dropTable('scraper_runs', true);
    }
}
