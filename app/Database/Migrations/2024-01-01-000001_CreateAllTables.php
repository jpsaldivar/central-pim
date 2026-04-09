<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAllTables extends Migration
{
    public function up()
    {
        // usuarios
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
            'email' => ['type' => 'VARCHAR', 'constraint' => 100],
            'password' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('usuarios');

        // tiendas
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
            'url_api' => ['type' => 'VARCHAR', 'constraint' => 255],
            'token_auth' => ['type' => 'TEXT'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tiendas');

        // proveedores
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
            'tiempo_encargo' => ['type' => 'INT', 'default' => 0],
            'contacto' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('proveedores');

        // marcas
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('marcas');

        // categorias
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 100],
            'descripcion' => ['type' => 'TEXT', 'null' => true],
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'default' => null],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('categorias');

        // productos
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 200],
            'marca_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'precio' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'],
            'precio_oferta' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'costo' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '0.00'],
            'stock_general' => ['type' => 'INT', 'default' => 0],
            'proveedor_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('productos');

        // producto_categoria
        $this->forge->addField([
            'producto_id' => ['type' => 'INT', 'unsigned' => true],
            'categoria_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['producto_id', 'categoria_id'], true);
        $this->forge->createTable('producto_categoria');

        // producto_tienda
        $this->forge->addField([
            'producto_id' => ['type' => 'INT', 'unsigned' => true],
            'tienda_id' => ['type' => 'INT', 'unsigned' => true],
            'valor_especifico' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'valor_oferta_esp' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'stock_especifico' => ['type' => 'INT', 'null' => true],
        ]);
        $this->forge->addKey(['producto_id', 'tienda_id'], true);
        $this->forge->createTable('producto_tienda');
    }

    public function down()
    {
        $this->forge->dropTable('producto_tienda', true);
        $this->forge->dropTable('producto_categoria', true);
        $this->forge->dropTable('productos', true);
        $this->forge->dropTable('categorias', true);
        $this->forge->dropTable('marcas', true);
        $this->forge->dropTable('proveedores', true);
        $this->forge->dropTable('tiendas', true);
        $this->forge->dropTable('usuarios', true);
    }
}
