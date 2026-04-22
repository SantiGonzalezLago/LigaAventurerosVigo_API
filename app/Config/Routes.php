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
  $routes->get('me', 'Auth::me', ['filter' => 'auth']);
  $routes->post('login', 'Auth::login');
  $routes->get('login/google', 'Auth::getGoogleClientId');
  $routes->post('login/google', 'Auth::googleLogin');
  $routes->post('login/admin', 'Auth::adminLogin', ['filter' => 'authadmin']);

  // Admin
  $routes->get('admin/control-panel', 'Admin::controlPanel', ['filter' => 'authadmin']);
  $routes->post('admin/user-list', 'Admin::userList', ['filter' => 'authadmin']);
  $routes->get('admin/user/(:segment)', 'Admin::user/$1', ['filter' => 'authadmin']);
  $routes->post('admin/ban-user', 'Admin::banUser', ['filter' => 'authadmin']);
  $routes->get('admin/unban/(:segment)', 'Admin::unbanUser/$1', ['filter' => 'authadmin']);
  $routes->post('admin/toggle-admin', 'Admin::toggleAdmin', ['filter' => 'authadmin']);
  $routes->post('admin/toggle-master', 'Admin::toggleMaster', ['filter' => 'authadmin']);
});
