<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class Auth implements FilterInterface {

  /**
   * Verificar token JWT antes de permitir acceso
   */
  public function before(RequestInterface $request, $arguments = null) {
    $response = service('response');

    // Obtener el header Authorization
    helper('user');
    $authHeader = user_get_authorization_header($request);

    if (empty($authHeader)) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'Token de autenticación requerido',
      ])->setStatusCode(401);
    }

    if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'Formato de token inválido',
      ])->setStatusCode(401);
    }

    $token = $matches[1];
    $key = env('JWT_SECRET');
    $expectedIssuer = rtrim((string) (env('JWT_ISSUER') ?: config('App')->baseURL), '/');

    if (empty($key)) {
      return $response->setJSON([
        'status' => 500,
        'error' => 'Error de configuración',
        'message' => 'JWTSecret no está configurado',
      ])->setStatusCode(500);
    }

    if (empty($expectedIssuer)) {
      return $response->setJSON([
        'status' => 500,
        'error' => 'Error de configuración',
        'message' => 'JWT issuer no está configurado',
      ])->setStatusCode(500);
    }

    try {
      $decoded = JWT::decode($token, new Key($key, 'HS256'));

      if (empty($decoded->user)) {
        return $response->setJSON([
          'status' => 401,
          'error' => 'No autorizado',
          'message' => 'Token inválido',
        ])->setStatusCode(401);
      }

      if (empty($decoded->iss) || $decoded->iss !== $expectedIssuer) {
        return $response->setJSON([
          'status' => 401,
          'error' => 'No autorizado',
          'message' => 'Issuer del token inválido',
        ])->setStatusCode(401);
      }

      if (empty($decoded->nbf)) {
        return $response->setJSON([
          'status' => 401,
          'error' => 'No autorizado',
          'message' => 'Token inválido',
        ])->setStatusCode(401);
      }

      $userModel = new UserModel();
      $user = $userModel->getUser((string) $decoded->user);

      if (!$user) {
        return $response->setJSON([
          'status' => 401,
          'error' => 'No autorizado',
          'message' => 'El usuario no existe',
        ])->setStatusCode(401);
      }

      if (user_is_banned($user->uid)) {
        return $response->setJSON([
          'status' => 403,
          'error' => 'No autorizado',
          'message' => 'El usuario está baneado',
        ])->setStatusCode(403);
      }
    } catch (ExpiredException $e) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'Token expirado',
      ])->setStatusCode(401);
    } catch (\Throwable $e) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'Token inválido',
      ])->setStatusCode(401);
    }
  }

  /**
   * No necesitamos hacer nada después de la respuesta
   */
  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
  }

}

