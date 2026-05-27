<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScraperProductos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'run_id'       => ['type' => 'INT', 'unsigned' => true],
            'tienda_id'    => ['type' => 'INT', 'unsigned' => true],
            'external_ref' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'ean'          => ['type' => 'VARCHAR', 'constraint' => 13,  'null' => true, 'default' => null],
            'sku'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'nombre'       => ['type' => 'VARCHAR', 'constraint' => 300],
            'url'          => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'default' => null],
            'precio_normal' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'precio_oferta' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'disponible'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'producto_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'   => ['type' => 'DATETIME'],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('run_id');
        $this->forge->addKey('tienda_id');
        $this->forge->addKey('producto_id');
        $this->forge->addKey('ean');
        $this->forge->addKey('sku');
        $this->forge->addForeignKey('run_id',      'scraper_runs', 'id', 'CASCADE',  'CASCADE');
        $this->forge->addForeignKey('tienda_id',   'tiendas',      'id', 'CASCADE',  'CASCADE');
        $this->forge->addForeignKey('producto_id', 'productos',    'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('scraper_productos');
    }

    public function down()
    {
        $this->forge->dropTable('scraper_productos', true);
    }
}
