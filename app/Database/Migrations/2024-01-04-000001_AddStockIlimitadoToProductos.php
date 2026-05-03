<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStockIlimitadoToProductos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('productos', [
            'stock_ilimitado' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'null'    => false,
                'default' => 0,
                'after'   => 'stock_general',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('productos', 'stock_ilimitado');
    }
}
