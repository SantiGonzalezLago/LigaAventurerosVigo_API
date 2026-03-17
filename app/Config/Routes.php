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
	$routes->post('login', 'Auth::login');
});
