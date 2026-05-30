<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBsaleVariantMap extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'woo_product_id'     => ['type' => 'INT', 'unsigned' => true],
            'sku'                => ['type' => 'VARCHAR', 'constraint' => 100],
            'bsale_variant_id'   => ['type' => 'INT', 'unsigned' => true],
            'bsale_product_name' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'default' => null],
            'activo'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'         => ['type' => 'DATETIME'],
            'updated_at'         => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('woo_product_id');
        $this->forge->addKey('sku');
        $this->forge->createTable('bsale_variant_map');
    }

    public function down(): void
    {
        $this->forge->dropTable('bsale_variant_map', true);
    }
}
