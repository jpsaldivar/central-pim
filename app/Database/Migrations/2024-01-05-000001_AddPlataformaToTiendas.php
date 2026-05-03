<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPlataformaToTiendas extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tiendas', [
            'plataforma' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
                'after'      => 'nombre',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tiendas', 'plataforma');
    }
}
