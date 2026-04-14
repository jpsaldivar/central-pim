<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSkuToProductos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('productos', [
            'sku' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'after'      => 'id',
            ],
        ]);

        $this->db->query('ALTER TABLE productos ADD UNIQUE KEY sku_unique (sku)');
    }

    public function down()
    {
        $this->forge->dropColumn('productos', 'sku');
    }
}
