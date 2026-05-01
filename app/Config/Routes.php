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

  // Public
  $routes->get('social-links', 'PublicController::socialLinks');

  // Autenticación
  $routes->post('login', 'Auth::login');
  $routes->get('login/google', 'Auth::getGoogleClientId');
  $routes->post('login/google', 'Auth::googleLogin');
  $routes->post('login/admin', 'Auth::adminLogin', ['filter' => 'authadmin']);

  // User
  $routes->get('me', 'User::me', ['filter' => 'auth']);
  $routes->post('update-settings', 'User::updateSettings', ['filter' => 'auth']);
  $routes->delete('delete-user', 'User::deleteUser', ['filter' => 'auth']);

  // Admin
  $routes->get('admin/control-panel', 'Admin\\Admin::controlPanel', ['filter' => 'authadmin']);
  $routes->post('admin/upload-log', 'Admin\\Admin::uploadLog', ['filter' => 'authadmin']);
  // Gestión de usuarios
  $routes->post('admin/user-list', 'Admin\\Users::userList', ['filter' => 'authadmin']);
  $routes->get('admin/user/(:segment)', 'Admin\\Users::user/$1', ['filter' => 'authadmin']);
  $routes->post('admin/ban-user', 'Admin\\Users::banUser', ['filter' => 'authadmin']);
  $routes->get('admin/unban/(:segment)', 'Admin\\Users::unbanUser/$1', ['filter' => 'authadmin']);
  $routes->post('admin/toggle-admin', 'Admin\\Users::toggleAdmin', ['filter' => 'authadmin']);
  $routes->post('admin/toggle-master', 'Admin\\Users::toggleMaster', ['filter' => 'authadmin']);
  $routes->post('admin/toggle-vip', 'Admin\\Users::toggleVip', ['filter' => 'authadmin']);
  // Gestión de configuraciones
  $routes->get('admin/settings/get', 'Admin\\Settings::settingsGet', ['filter' => 'authadmin']);
  $routes->post('admin/settings/update', 'Admin\\Settings::settingsUpdate', ['filter' => 'authadmin']);

  // Cron
  $routes->get('cron/delete-users-by-request', 'Cron::deleteUsersByRequest', ['filter' => 'cronapikey']);
  $routes->get('cron/delete-unused-avatars', 'Cron::deleteUnusedAvatars', ['filter' => 'cronapikey']);

});
