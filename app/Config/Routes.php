<?php

use Config\Services;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('v1', ['namespace' => 'App\Controllers\V1'], static function ($routes) {
	$routes->options('(:any)', static function () {
		return Services::response()->setStatusCode(204);
	});

	// Documentación
	$routes->get('docs', 'Docs::index');

	// Rutas de la API
	$routes->post('login', 'Auth::login');
});
