<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Dashboard::index');
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::doLogin');
$routes->get('/logout', 'Auth::logout');

// Dashboard
$routes->get('/dashboard', 'Dashboard::index');

// Marcas
$routes->get('/marcas', 'Marcas::index');
$routes->get('/marcas/create', 'Marcas::create');
$routes->post('/marcas/store', 'Marcas::store');
$routes->get('/marcas/edit/(:num)', 'Marcas::edit/$1');
$routes->post('/marcas/update/(:num)', 'Marcas::update/$1');
$routes->get('/marcas/delete/(:num)', 'Marcas::delete/$1');

// Proveedores
$routes->get('/proveedores', 'Proveedores::index');
$routes->get('/proveedores/create', 'Proveedores::create');
$routes->post('/proveedores/store', 'Proveedores::store');
$routes->get('/proveedores/edit/(:num)', 'Proveedores::edit/$1');
$routes->post('/proveedores/update/(:num)', 'Proveedores::update/$1');
$routes->get('/proveedores/delete/(:num)', 'Proveedores::delete/$1');

// Categorias
$routes->get('/categorias', 'Categorias::index');
$routes->get('/categorias/create', 'Categorias::create');
$routes->post('/categorias/store', 'Categorias::store');
$routes->get('/categorias/edit/(:num)', 'Categorias::edit/$1');
$routes->post('/categorias/update/(:num)', 'Categorias::update/$1');
$routes->get('/categorias/delete/(:num)', 'Categorias::delete/$1');

// Tiendas
$routes->get('/tiendas', 'Tiendas::index');
$routes->get('/tiendas/create', 'Tiendas::create');
$routes->post('/tiendas/store', 'Tiendas::store');
$routes->get('/tiendas/edit/(:num)', 'Tiendas::edit/$1');
$routes->post('/tiendas/update/(:num)', 'Tiendas::update/$1');
$routes->get('/tiendas/delete/(:num)', 'Tiendas::delete/$1');

// Productos
$routes->get('/productos', 'Productos::index');
$routes->get('/productos/create', 'Productos::create');
$routes->post('/productos/store', 'Productos::store');
$routes->get('/productos/edit/(:num)', 'Productos::edit/$1');
$routes->post('/productos/update/(:num)', 'Productos::update/$1');
$routes->get('/productos/delete/(:num)', 'Productos::delete/$1');

// Migraciones
$routes->get('/migraciones', 'Migraciones::index');
$routes->post('/migraciones/ejecutar', 'Migraciones::ejecutar');
$routes->get('/migraciones/logs', 'Migraciones::logs');
