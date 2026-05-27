<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Scrapers extends BaseConfig
{
    /**
     * Mapa de tiendas scraper con sus categorías configuradas.
     *
     * La clave debe coincidir con tiendas.plataforma en la base de datos.
     * Para agregar una categoría: añadir entrada ['nombre', 'url'] al array 'categorias'.
     * Para deshabilitar una categoría: comentar o eliminar su entrada.
     *
     * @var array<string, array{nombre: string, url_base: string, categorias: list<array{nombre: string, url: string}>}>
     */
    public array $tiendas = [

        'dronestore_scraper' => [
            'nombre'    => 'Dronestore Chile',
            'url_base'  => 'https://dronestore.cl',
            'categorias' => [
                ['nombre' => 'Drones con cámara', 'url' => 'https://dronestore.cl/3-drones-con-camara'],
            ],
        ],

        'gopro_scraper' => [
            'nombre'    => 'GoPro Chile',
            'url_base'  => 'https://www.gopro.cl',
            'categorias' => [
                ['nombre' => 'Cámaras',    'url' => 'https://www.gopro.cl/categoria-producto/camaras/'],
                ['nombre' => 'Accesorios', 'url' => 'https://www.gopro.cl/categoria-producto/accesorios/'],
            ],
        ],

        'sony_scraper' => [
            'nombre'    => 'Sony Store Chile',
            'url_base'  => 'https://store.sony.cl',
            'categorias' => [
                ['nombre' => 'Lentes', 'url' => 'https://store.sony.cl/camaras/lentes'],
            ],
        ],

    ];
}
