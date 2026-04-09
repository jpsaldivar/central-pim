<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        // Admin user
        $this->db->table('usuarios')->insert([
            'nombre' => 'Administrador',
            'email' => 'admin@centralpim.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Sample brands
        $this->db->table('marcas')->insertBatch([
            ['nombre' => 'Samsung'],
            ['nombre' => 'Apple'],
            ['nombre' => 'Sony'],
        ]);

        // Sample suppliers
        $this->db->table('proveedores')->insertBatch([
            ['nombre' => 'Proveedor Central', 'tiempo_encargo' => 3, 'contacto' => 'contacto@proveedor.com'],
            ['nombre' => 'Distribuidora Norte', 'tiempo_encargo' => 7, 'contacto' => '+1-555-0100'],
        ]);

        // Sample categories
        $this->db->table('categorias')->insertBatch([
            ['nombre' => 'Electrónica', 'descripcion' => 'Productos electrónicos', 'parent_id' => null],
            ['nombre' => 'Celulares', 'descripcion' => 'Teléfonos móviles', 'parent_id' => 1],
            ['nombre' => 'Laptops', 'descripcion' => 'Computadoras portátiles', 'parent_id' => 1],
            ['nombre' => 'Accesorios', 'descripcion' => 'Accesorios varios', 'parent_id' => null],
        ]);

        // Sample stores
        $this->db->table('tiendas')->insertBatch([
            ['nombre' => 'Tienda Principal', 'url_api' => 'https://tienda1.ejemplo.com/api/sync', 'token_auth' => 'tok_' . bin2hex(random_bytes(16))],
            ['nombre' => 'Tienda Online', 'url_api' => 'https://tienda2.ejemplo.com/api/sync', 'token_auth' => 'tok_' . bin2hex(random_bytes(16))],
        ]);
    }
}
