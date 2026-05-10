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

  // Game Systems (admin)
  $routes->get('game-systems', 'GameSystems::index');
  $routes->get('game-systems/(:segment)', 'GameSystems::show/$1');
  $routes->post('game-systems/add', 'GameSystems::add', ['filter' => 'authadmin']);
  $routes->post('game-systems/update/(:num)', 'GameSystems::update/$1', ['filter' => 'authadmin']);
  $routes->get('game-systems/(:num)/tiers', 'GameSystems::tiers/$1');
  $routes->post('game-systems/(:num)/tiers/update', 'GameSystems::updateTiers/$1', ['filter' => 'authadmin']);
  $routes->get('game-systems/(:num)/setting', 'GameSystems::settings/$1');
  $routes->post('game-systems/(:num)/setting/add', 'GameSystems::addSetting/$1', ['filter' => 'authadmin']);
  $routes->post('game-systems/(:num)/setting/(:num)/update', 'GameSystems::updateSetting/$1/$2', ['filter' => 'authadmin']);
  $routes->delete('game-systems/(:num)/setting/(:num)/delete', 'GameSystems::deleteSetting/$1/$2', ['filter' => 'authadmin']);
  $routes->get('game-systems/(:num)/species', 'GameSystems::species/$1');
  $routes->post('game-systems/(:num)/species/add', 'GameSystems::addSpecies/$1', ['filter' => 'authadmin']);
  $routes->post('game-systems/(:num)/species/(:num)/update', 'GameSystems::updateSpecies/$1/$2', ['filter' => 'authadmin']);
  $routes->delete('game-systems/(:num)/species/(:num)/delete', 'GameSystems::deleteSpecies/$1/$2', ['filter' => 'authadmin']);
  $routes->get('game-systems/(:num)/class', 'GameSystems::classes/$1');
  $routes->post('game-systems/(:num)/class/add', 'GameSystems::addClass/$1', ['filter' => 'authadmin']);
  $routes->post('game-systems/(:num)/class/(:num)/update', 'GameSystems::updateClass/$1/$2', ['filter' => 'authadmin']);
  $routes->delete('game-systems/(:num)/class/(:num)/delete', 'GameSystems::deleteClass/$1/$2', ['filter' => 'authadmin']);

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
  $routes->get('cron/delete-unused-files', 'Cron::deleteUnusedFiles', ['filter' => 'cronapikey']);

});
