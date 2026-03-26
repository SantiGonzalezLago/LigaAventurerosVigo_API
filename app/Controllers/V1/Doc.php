<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;

class Doc extends BaseController {

  public function index() {
    return view('docs/index', [
      'basePath' => '/v1',
      'endpoints' => $this->getEndpoints(),
    ]);
  }

  private function getEndpoints(): array {
    return [
      [
        'name' => 'Login con email y contraseña',
        'method' => 'POST',
        'path' => '/login',
        'description' => 'Autentica al usuario con email y contraseña y devuelve el JWT de la API.',
        'request' => [
          ['name' => 'user', 'type' => 'string', 'required' => true, 'example' => 'usuario@dominio.com'],
          ['name' => 'password', 'type' => 'string', 'required' => true, 'example' => '********'],
        ],
      ],
      [
        'name' => 'Usuario autenticado',
        'method' => 'GET',
        'path' => '/me',
        'description' => 'Devuelve los datos del usuario logeado usando el JWT del header Authorization (Bearer token).',
        'authRequired' => true,
        'request' => [],
      ],
      [
        'name' => 'Obtener Google Client ID',
        'method' => 'GET',
        'path' => '/login/google',
        'description' => 'Devuelve el google_client_id configurado en el servidor.',
        'request' => [],
      ],
      [
        'name' => 'Login con Google',
        'method' => 'POST',
        'path' => '/login/google',
        'description' => 'Valida el id_token de Google, crea/recupera el usuario y devuelve JWT de la API.',
        'request' => [
          ['name' => 'id_token', 'type' => 'string', 'required' => true, 'example' => 'eyJhbGciOiJSUzI1NiIs'],
        ],
      ],
      [
        'name' => 'Panel de control (admin)',
        'method' => 'GET',
        'path' => '/admin/control-panel',
        'description' => 'Devuelve los contadores de usuarios para el panel de navegación (confirmados, no confirmados y baneados).',
        'authRequired' => true,
        'request' => [],
      ],
      [
        'name' => 'Listado de usuarios (admin)',
        'method' => 'POST',
        'path' => '/admin/user-list',
        'description' => 'Devuelve un listado paginado de usuarios con orden y búsqueda opcional.',
        'authRequired' => true,
        'request' => [
          ['name' => 'page', 'type' => 'int', 'required' => false, 'example' => 1],
          ['name' => 'per_page', 'type' => 'int', 'required' => false, 'example' => 20],
          ['name' => 'order_by', 'type' => 'string', 'required' => false, 'example' => 'date_created'],
          ['name' => 'order_dir', 'type' => 'string', 'required' => false, 'example' => 'desc'],
          ['name' => 'q', 'type' => 'string', 'required' => false, 'example' => ''],
        ],
      ],
      [
        'name' => 'Detalle de usuario (admin)',
        'method' => 'GET',
        'path' => '/admin/user/{uid}',
        'description' => 'Devuelve los datos de un usuario y su historial de bans.',
        'authRequired' => true,
        'request' => [
          ['name' => 'uid', 'type' => 'string', 'required' => true, 'example' => 'ABC123DEF45'],
        ],
      ],
    ];
  }
}