<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;

class Auth extends BaseController {
  use ResponseTrait;

  public function login() {
    $email = $this->request->getVar('user');
    $password = $this->request->getVar('password');

    if ($email === '' || $password === '') {
      return $this->respond([
        'message' => 'error',
        'error' => 'Usuario y contraseña son obligatorios',
      ], 400);
    }

    $userModel = new UserModel();
    $user = $userModel->where('email', $email)->first();

    if (!$user || !password_verify($password, $user->password)) {
      return $this->respond([
        'message' => 'error',
        'error' => 'El usuario no existe o la contraseña es incorrecta',
      ], 401);
    }

    if ((int) $user->banned === 1) {
      return $this->respond([
        'message' => 'error',
        'error' => 'El usuario está baneado',
      ], 403);
    }

    $response = [
      'message' => 'ok',
      'user' => $this->generateUserdata($user),
    ];

    return $this->respond($response, 200);
  }

  private function generateUserdata($user) {
    return [
      'uid' => $user->uid,
      'jwt' => $this->generateJWT($user),
      'name' => $user->name,
      'email' => $user->email,
      'verified' => $user->verified,
      'master' => $user->master,
      'admin' => $user->admin,
    ];
  }

  private function generateJWT($user) {
    $key = env('JWT_SECRET');
    $issuer = rtrim((string) (env('JWT_ISSUER') ?: config('App')->baseURL), '/');

    if (empty($key)) {
      throw new \RuntimeException('JWTSecret no está configurado');
    }

    if (empty($issuer)) {
      throw new \RuntimeException('JWT issuer no está configurado');
    }

    $issuedAt = time();
    $expirationTime = $issuedAt + (90 * 24 * 60 * 60); // 90 días de validez

    $payload = [
      'iss' => $issuer,
      'iat' => $issuedAt,
      'nbf' => $issuedAt,
      'exp' => $expirationTime,
      'user' => $user->uid
    ];

    return JWT::encode($payload, $key, 'HS256');
  }
}