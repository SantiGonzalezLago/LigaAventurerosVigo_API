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
    ];
  }
}