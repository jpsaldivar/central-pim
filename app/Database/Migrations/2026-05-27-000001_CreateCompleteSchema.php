<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompleteSchema extends Migration
{
    public function up(): void
    {
        // usuarios
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('usuarios');

        // marcas
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('marcas');

        // proveedores
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'tiempo_encargo' => ['type' => 'INT', 'default' => 0],
            'contacto'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('proveedores');

        // categorias (jerarquía con parent_id)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'descripcion' => ['type' => 'TEXT', 'null' => true, 'default' => null],
            'parent_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('parent_id');
        $this->forge->createTable('categorias');

        // tiendas (plataforma incluida desde el inicio)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'plataforma' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null],
            'url_api'    => ['type' => 'VARCHAR', 'constraint' => 255],
            'token_auth' => ['type' => 'TEXT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tiendas');

        // productos (sku y stock_ilimitado incluidos desde el inicio)
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'sku'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'nombre'         => ['type' => 'VARCHAR', 'constraint' => 200],
            'marca_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'precio'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'],
            'precio_oferta'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => null],
            'costo'          => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'],
            'stock_general'  => ['type' => 'INT', 'default' => 0],
            'stock_ilimitado'=> ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'proveedor_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey('marca_id');
        $this->forge->addKey('proveedor_id');
        $this->forge->createTable('productos');

        // producto_categoria (N:M)
        $this->forge->addField([
            'producto_id'  => ['type' => 'INT', 'unsigned' => true],
            'categoria_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['producto_id', 'categoria_id'], true);
        $this->forge->createTable('producto_categoria');

        // producto_tienda (N:M con datos extra y external_id)
        $this->forge->addField([
            'producto_id'      => ['type' => 'INT', 'unsigned' => true],
            'tienda_id'        => ['type' => 'INT', 'unsigned' => true],
            'valor_especifico' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => null],
            'valor_oferta_esp' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'default' => null],
            'stock_especifico' => ['type' => 'INT', 'null' => true, 'default' => null],
            'external_id'      => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'comment'    => 'ID del producto en la plataforma externa (Jumpseller, WooCommerce, etc.)',
            ],
        ]);
        $this->forge->addKey(['producto_id', 'tienda_id'], true);
        $this->forge->createTable('producto_tienda');

        // migration_logs
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tipo'            => ['type' => 'VARCHAR', 'constraint' => 50],
            'sku'             => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            'nombre_producto' => ['type' => 'VARCHAR', 'constraint' => 200, 'default' => ''],
            'accion'          => ['type' => 'VARCHAR', 'constraint' => 50],
            'estado'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'mensaje'         => ['type' => 'TEXT'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tipo', 'estado']);
        $this->forge->addKey('sku');
        $this->forge->addKey('created_at');
        $this->forge->createTable('migration_logs');

        // scraper_runs
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tienda_id'         => ['type' => 'INT', 'unsigned' => true],
            'estado'            => [
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

        // scraper_productos
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'run_id'        => ['type' => 'INT', 'unsigned' => true],
            'tienda_id'     => ['type' => 'INT', 'unsigned' => true],
            'external_ref'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'ean'           => ['type' => 'VARCHAR', 'constraint' => 13, 'null' => true, 'default' => null],
            'sku'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'default' => null],
            'nombre'        => ['type' => 'VARCHAR', 'constraint' => 300],
            'url'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'default' => null],
            'precio_normal' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'precio_oferta' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'disponible'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'producto_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
            'created_at'    => ['type' => 'DATETIME'],
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

    public function down(): void
    {
        $this->forge->dropTable('scraper_productos',  true);
        $this->forge->dropTable('scraper_runs',       true);
        $this->forge->dropTable('migration_logs',     true);
        $this->forge->dropTable('producto_tienda',    true);
        $this->forge->dropTable('producto_categoria', true);
        $this->forge->dropTable('productos',          true);
        $this->forge->dropTable('tiendas',            true);
        $this->forge->dropTable('categorias',         true);
        $this->forge->dropTable('proveedores',        true);
        $this->forge->dropTable('marcas',             true);
        $this->forge->dropTable('usuarios',           true);
    }
}
