<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;

abstract class BaseApiController extends BaseController {
  use ResponseTrait;

  protected function generateUserdata(object $user): array {
    return [
      'uid' => $user->uid,
      'jwt' => $this->generateJWT($user),
      'name' => $user->name,
      'email' => $user->email,
      'avatar' => build_avatar_url($user->avatar ?? null),
      'verified' => (bool) $user->verified,
      'master' => (bool) $user->master,
      'admin' => (bool) $user->admin,
    ];
  }

  protected function generateJWT(object $user): string {
    $key = env('JWT_SECRET');
    $issuer = rtrim((string) (env('JWT_ISSUER') ?: config('App')->baseURL), '/');

    if (empty($key)) {
      throw new \RuntimeException('JWTSecret no está configurado');
    }

    if (empty($issuer)) {
      throw new \RuntimeException('JWT issuer no está configurado');
    }

    $issuedAt = time();
    $expirationTime = $issuedAt + (48 * 60 * 60); // 48 horas de validez

    $payload = [
      'iss' => $issuer,
      'iat' => $issuedAt,
      'nbf' => $issuedAt,
      'exp' => $expirationTime,
      'user' => $user->uid,
    ];

    return JWT::encode($payload, $key, 'HS256');
  }
}