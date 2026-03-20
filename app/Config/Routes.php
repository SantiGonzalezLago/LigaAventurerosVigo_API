<?php

use Config\Services;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', static function () {
	return redirect()->to('/v1/doc');
});

$routes->group('v1', ['namespace' => 'App\Controllers\V1'], static function ($routes) {
	$routes->get('/', static function () {
		return redirect()->to('/v1/doc');
	});
	$routes->options('(:any)', static function () {
		return Services::response()->setStatusCode(204);
	});

	// Documentación
	$routes->get('doc', 'Doc::index');

	// Autenticación
	$routes->post('login', 'Auth::login');
	$routes->get('login/google', 'Auth::getGoogleClientId');
	$routes->post('login/google', 'Auth::googleLogin');
});
