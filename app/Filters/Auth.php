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

  private function getAuthorizationHeader(RequestInterface $request): ?string {
    $authHeader = trim($request->getHeaderLine('Authorization'));

    if ($authHeader !== '') {
      return $authHeader;
    }

    $serverCandidates = [
      'HTTP_AUTHORIZATION',
      'REDIRECT_HTTP_AUTHORIZATION',
    ];

    foreach ($serverCandidates as $key) {
      $value = $request->getServer($key);

      if (is_string($value) && trim($value) !== '') {
        return trim($value);
      }
    }

    if (function_exists('apache_request_headers')) {
      $headers = apache_request_headers();

      if (is_array($headers)) {
        foreach ($headers as $name => $value) {
          if (strcasecmp((string) $name, 'Authorization') === 0 && is_string($value) && trim($value) !== '') {
            return trim($value);
          }
        }
      }
    }

    return null;
  }

  /**
   * Verificar token JWT antes de permitir acceso
   */
  public function before(RequestInterface $request, $arguments = null) {
    $response = service('response');

    // Obtener el header Authorization
    $authHeader = $this->getAuthorizationHeader($request);

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

      if ((int) $user->banned === 1) {
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

