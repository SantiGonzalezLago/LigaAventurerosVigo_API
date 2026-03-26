<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthAdmin implements FilterInterface {

  /**
   * Verificar token JWT y rol admin antes de permitir acceso
   */
  public function before(RequestInterface $request, $arguments = null) {
    $authFilter = new Auth();
    $authResult = $authFilter->before($request, $arguments);

    if ($authResult !== null) {
      return $authResult;
    }

    helper('user');
    $response = service('response');
    $authHeader = user_get_authorization_header($request);

    if (empty($authHeader) || !preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'No autorizado',
      ])->setStatusCode(401);
    }

    $key = env('JWT_SECRET');

    if (empty($key)) {
      return $response->setJSON([
        'status' => 500,
        'error' => 'Error de configuración',
        'message' => 'JWTSecret no está configurado',
      ])->setStatusCode(500);
    }

    try {
      $decoded = JWT::decode($matches[1], new Key($key, 'HS256'));

      if (empty($decoded->user)) {
        return $response->setJSON([
          'status' => 401,
          'error' => 'No autorizado',
          'message' => 'No autorizado',
        ])->setStatusCode(401);
      }

      $userModel = new UserModel();
      $user = $userModel->getUser((string) $decoded->user);

      if (!$user || !$user->admin) {
        return $response->setJSON([
          'status' => 401,
          'error' => 'No autorizado',
          'message' => 'No autorizado',
        ])->setStatusCode(401);
      }
    } catch (\Throwable $e) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'No autorizado',
      ])->setStatusCode(401);
    }
  }

  /**
   * No necesitamos hacer nada después de la respuesta
   */
  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
  }

}
