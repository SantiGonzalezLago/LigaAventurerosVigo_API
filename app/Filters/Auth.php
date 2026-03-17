<?php

namespace App\Filters;

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
    $authHeader = $request->getHeaderLine('Authorization');

    if (empty($authHeader)) {
      return $response->setJSON([
        'status' => 401,
        'error' => 'No autorizado',
        'message' => 'Token de autenticación requerido',
      ])->setStatusCode(401);
    }

    // TODO: Implementar verificación de token JWT aquí
  }

  /**
   * No necesitamos hacer nada después de la respuesta
   */
  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
  }

}

