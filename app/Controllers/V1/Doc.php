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
        'name' => 'Login como admin',
        'method' => 'POST',
        'path' => '/login/admin',
        'description' => 'Permite a un admin autenticarse como otro usuario y obtener su JWT. Requiere Authorization: Bearer <jwt> de un usuario admin.',
        'authRequired' => true,
        'request' => [
          ['name' => 'uid', 'type' => 'string', 'required' => true, 'example' => 'ABC123DEF45'],
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
      [
        'name' => 'Banear usuario (admin)',
        'method' => 'POST',
        'path' => '/admin/ban-user',
        'description' => 'Registra un ban para un usuario. El campo banned_by se extrae del JWT del admin autenticado y date_start se establece automaticamente al recibir la peticion.',
        'authRequired' => true,
        'request' => [
          ['name' => 'uid', 'type' => 'string', 'required' => true, 'example' => 'ABC123DEF45'],
          ['name' => 'permanent', 'type' => 'int', 'required' => true, 'example' => 1],
          ['name' => 'date_end', 'type' => 'string', 'required' => false, 'example' => '2026-05-01 12:00:00'],
          ['name' => 'reason', 'type' => 'string', 'required' => true, 'example' => 'Incumplimiento de normas'],
        ],
      ],
      [
        'name' => 'Desbanear usuario (admin)',
        'method' => 'GET',
        'path' => '/admin/unban/{uid}',
        'description' => 'Desbanea a un usuario poniendo todos sus bans activos a permanente=0 y date_end a la fecha-hora actual.',
        'authRequired' => true,
        'request' => [
          ['name' => 'uid', 'type' => 'string', 'required' => true, 'example' => 'ABC123DEF45'],
        ],
      ],
      [
        'name' => 'Toggle admin (admin)',
        'method' => 'POST',
        'path' => '/admin/toggle-admin',
        'description' => 'Activa o desactiva el rol admin de un usuario. Solo accesible para administradores. No permite modificar tus propios permisos de admin ni activar admin a usuarios no verificados.',
        'authRequired' => true,
        'request' => [
          ['name' => 'uid', 'type' => 'string', 'required' => true, 'example' => 'ABC123DEF45'],
          ['name' => 'state', 'type' => 'int', 'required' => true, 'example' => 1],
        ],
      ],
      [
        'name' => 'Toggle master (admin)',
        'method' => 'POST',
        'path' => '/admin/toggle-master',
        'description' => 'Activa o desactiva el rol master de un usuario. Solo accesible para administradores. No permite activar master a usuarios no verificados.',
        'authRequired' => true,
        'request' => [
          ['name' => 'uid', 'type' => 'string', 'required' => true, 'example' => 'ABC123DEF45'],
          ['name' => 'state', 'type' => 'int', 'required' => true, 'example' => 1],
        ],
      ],
      [
        'name' => 'Obtener settings (admin)',
        'method' => 'GET',
        'path' => '/admin/settings/get',
        'description' => 'Devuelve la lista completa de settings del sistema.',
        'authRequired' => true,
        'request' => [],
      ],
      [
        'name' => 'Actualizar setting (admin)',
        'method' => 'POST',
        'path' => '/admin/settings/update',
        'description' => 'Actualiza el valor de una setting existente identificada por su clave.',
        'authRequired' => true,
        'request' => [
          ['name' => 'key', 'type' => 'string', 'required' => true, 'example' => 'test'],
          ['name' => 'value', 'type' => 'string', 'required' => true, 'example' => 'abc123'],
        ],
      ],
    ];
  }
}