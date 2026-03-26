<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use App\Models\SettingsModel;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;

class Auth extends BaseController {
  use ResponseTrait;

  public function __construct() {
    helper('uid');
    $this->userModel = new UserModel();
  }

  /**
   * Endpoint: POST /v1/login
   *
   * Recibe:
   * - user (string): email del usuario.
   * - password (string): contraseña en texto plano.
   *
   * Devuelve:
   * - 200: { message: "ok", user: { uid, jwt, name, email, avatar, verified, master, admin } }
   * - 400: { message: "error", error: "Usuario y contraseña son obligatorios" }
   * - 401: { message: "error", error: "Usuario o contraseña incorrectos" }
   * - 403: { message: "error", error: "El usuario está baneado" }
   */
  public function login() {
    $email = $this->request->getVar('user');
    $password = $this->request->getVar('password');

    if ($email === '' || $password === '') {
      return $this->respond([
        'message' => 'Usuario y contraseña son obligatorios',
      ], 400);
    }

    $user = $this->userModel->getUserByEmail($email);

    if (!$user || !password_verify($password, $user->password)) {
      return $this->respond([
        'message' => 'Usuario o contraseña incorrectos',
      ], 401);
    }

    if (user_is_banned($user->uid)) {
      return $this->respond([
        'message' => 'El usuario está baneado',
      ], 403);
    }

    $response = [
      'message' => 'ok',
      'user' => $this->generateUserdata($user),
    ];

    return $this->respond($response, 200);
  }

  /**
   * Endpoint: GET /v1/me
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   *
   * Devuelve:
   * - 200: { message: "ok", user: { uid, jwt, name, email, avatar, verified, master, admin } }
   * - 401: { message: "No autorizado" }
   */
  public function me() {
    $user = $this->getUserFromJwt();

    return $this->respond([
      'message' => 'ok',
      'user' => $this->generateUserdata($user),
    ], 200);
  }

  private function generateUserdata($user) {
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

  /**
   * Endpoint: GET /v1/login/google
   *
   * Recibe:
   * - Sin parámetros.
   *
   * Devuelve:
   * - 200: { message: "ok", google_client_id: "..." }
   * - 500: { message: "error", error: "Google OAuth no está configurado" }
   */
  public function getGoogleClientId() {
    $settingsModel = new SettingsModel();
    $clientId = $settingsModel->getSetting('google_client_id');

    if (empty($clientId)) {
      return $this->respond([
        'message' => 'Google OAuth no está configurado',
      ], 500);
    }

    return $this->respond([
      'message' => 'ok',
      'google_client_id' => $clientId,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/login/google
   *
   * Recibe:
   * - id_token (string): ID token de Google obtenido en el cliente.
   *
   * Devuelve:
   * - 200: { message: "ok", user: { uid, jwt, name, email, avatar, verified, master, admin } }
   * - 400: { message: "error", error: "El token de Google es obligatorio" }
   * - 401: { message: "error", error: "Token de Google inválido o expirado" }
   * - 403: { message: "error", error: "El usuario está baneado" }
   * - 500: { message: "error", error: "Google OAuth no está configurado" }
   */
  public function googleLogin() {
    $idToken = $this->request->getVar('id_token');

    if (empty($idToken)) {
      return $this->respond([
        'message' => 'El token de Google es obligatorio',
      ], 400);
    }

    $settingsModel = new SettingsModel();
    $clientId = $settingsModel->getSetting('google_client_id');

    if (empty($clientId)) {
      return $this->respond([
        'message' => 'Google OAuth no está configurado',
      ], 500);
    }

    $googleUser = $this->verifyGoogleToken($idToken, $clientId);

    if ($googleUser === null) {
      return $this->respond([
        'message' => 'Token de Google inválido o expirado',
      ], 401);
    }

    $user = $this->userModel->getUserByProvider('google', $googleUser['sub']);

    if ($user === null) {
      $user = $this->migrateLegacyUser(
        $googleUser['email'],
        'google',
        $googleUser['sub'],
        $googleUser['picture'] ?? null
      );
    }

    if ($user === null) {
      $user = $this->registerUser(
        $googleUser['email'],
        $googleUser['name'],
        'google',
        $googleUser['sub'],
        $googleUser['picture'] ?? null,
      );
    }

    if ($user === null) {
      return $this->respond([
        'message' => 'No se pudo crear la cuenta de usuario',
      ], 500);
    }

    if (user_is_banned($user->uid)) {
      return $this->respond([
        'message' => 'El usuario está baneado',
      ], 403);
    }

    return $this->respond([
      'message' => 'ok',
      'user'    => $this->generateUserdata($user),
    ], 200);
  }

  private function verifyGoogleToken(string $idToken, string $clientId): ?array {
    $url  = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $curl = \Config\Services::curlrequest();

    try {
      $response   = $curl->get($url, ['http_errors' => false]);
      $statusCode = $response->getStatusCode();

      if ($statusCode !== 200) {
        return null;
      }

      $data = json_decode($response->getBody(), true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
      }

      // Validate that the token was issued for our app
      if (($data['aud'] ?? '') !== $clientId) {
        return null;
      }

      // Only accept tokens with a verified email
      if (($data['email_verified'] ?? 'false') !== 'true') {
        return null;
      }

      return [
        'sub'   => $data['sub'],
        'email' => $data['email'],
        'name'  => $data['given_name'] ?? $data['name'] ?? $data['email'],
        'picture' => $data['picture'] ?? null,
      ];
    } catch (\Exception $e) {
      return null;
    }
  }

  private function migrateLegacyUser($email, $provider, $providerId, $avatarUrl = null) {
    $legacyUser = $this->userModel->getLegacyUserByEmail($email);

    if (!$legacyUser) {
      return null;
    }

    $success = $this->userModel->insertUserProvider($legacyUser->uid, $provider, $providerId);
    if (!$success) {
      return null;
    }

    if (!empty($avatarUrl)) {
      $avatarPath = $this->downloadAvatar($legacyUser->uid, $avatarUrl);

      if ($avatarPath !== null) {
        $this->userModel->update($legacyUser->uid, ['avatar' => $avatarPath]);
      }
    }

    return $legacyUser;
  }

  private function registerUser($email, $name, $provider, $providerId, $avatarUrl = null) {
    $uid = generate_uid();
    $userData = [
      'uid' => $uid,
      'email' => $email,
      'name' => $name,
      'verified' => 0,
    ];

    try {
      $this->userModel->insert($userData);
    } catch (\Exception $e) {
      return null;
    }

    $success = $this->userModel->insertUserProvider($uid, $provider, $providerId);

    if (!$success) {
      $this->userModel->delete($uid);
      return null;
    }

    if (!empty($avatarUrl)) {
      $avatarPath = $this->downloadAvatar($uid, $avatarUrl);

      if ($avatarPath !== null) {
        $this->userModel->update($uid, ['avatar' => $avatarPath]);
      }
    }

    return $this->userModel->getUser($uid);
  }

  private function downloadAvatar(string $uid, string $avatarUrl): ?string {
    $avatarDirectory = FCPATH . 'images/avatar/';

    if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
      return null;
    }

    $curl = \Config\Services::curlrequest();

    try {
      $response = $curl->get($avatarUrl, ['http_errors' => false]);
    } catch (\Exception $e) {
      return null;
    }

    if ($response->getStatusCode() !== 200) {
      return null;
    }

    $body = (string) $response->getBody();

    if ($body === '') {
      return null;
    }

    $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
    $extension = $this->resolveAvatarExtension($contentType, $avatarUrl);

    if ($extension === null) {
      return null;
    }

    $filename = $uid . '_' . date('YmdHis') . '.' . $extension;
    $relativePath = '/images/avatar/' . $filename;
    $targetPath = FCPATH . 'images/avatar/' . $filename;

    if (file_put_contents($targetPath, $body) === false) {
      return null;
    }

    return $filename;
  }

  private function resolveAvatarExtension(string $contentType, string $avatarUrl): ?string {
    $extensionsByType = [
      'image/jpeg' => 'jpg',
      'image/jpg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
      'image/gif' => 'gif',
    ];

    if (isset($extensionsByType[$contentType])) {
      return $extensionsByType[$contentType];
    }

    $path = parse_url($avatarUrl, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
      return null;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($extension === 'jpeg') {
      return 'jpg';
    }

    if (in_array($extension, ['jpg', 'png', 'webp', 'gif'], true)) {
      return $extension;
    }

    return null;
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
    $expirationTime = $issuedAt + (48 * 60 * 60); // 48 horas de validez

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