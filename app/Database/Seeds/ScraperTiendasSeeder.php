<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ScraperTiendasSeeder extends Seeder
{
    public function run()
    {
        $tiendas = [
            [
                'nombre'     => 'Dronestore Chile',
                'plataforma' => 'dronestore_scraper',
                'url_api'    => 'https://dronestore.cl',
                'token_auth' => '',
            ],
            [
                'nombre'     => 'GoPro Chile',
                'plataforma' => 'gopro_scraper',
                'url_api'    => 'https://www.gopro.cl',
                'token_auth' => '',
            ],
            [
                'nombre'     => 'Sony Store Chile',
                'plataforma' => 'sony_scraper',
                'url_api'    => 'https://store.sony.cl',
                'token_auth' => '',
            ],
            [
                'nombre'     => 'Hanuman',
                'plataforma' => 'hanuman_scraper',
                'url_api'    => 'https://consultas.hanuman.cl',
                'token_auth' => '',
            ],
        ];

        foreach ($tiendas as $tienda) {
            $existe = $this->db->table('tiendas')
                ->where('plataforma', $tienda['plataforma'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db->table('tiendas')->insert($tienda);
            }
        }
    }
}
