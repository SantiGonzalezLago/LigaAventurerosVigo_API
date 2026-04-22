<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class Admin extends BaseController {
  use ResponseTrait;

  public function __construct() {
    $this->userModel = new UserModel();
  }

  /**
   * Endpoint: GET /v1/admin/control-panel
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   *
   * Devuelve:
   * - 200: {
   *   message: "ok",
   *   users: {
   *     confirmed: number,
   *     unconfirmed: number,
   *     banned: number
   *   }
   * }
   * - 401: { message: "No autorizado" }
   */
  public function controlPanel() {
    $stats = $this->userModel->getControlPanelUserStats();

    return $this->respond([
      'message' => 'ok',
      'users' => [
        'confirmed' => (int) ($stats->confirmed ?? 0),
        'unconfirmed' => (int) ($stats->unconfirmed ?? 0),
        'banned' => (int) ($stats->banned ?? 0),
      ],
    ], 200);
  }

  /**
   * Endpoint: POST /v1/admin/user-list
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - page (int, optional, default: 1)
   * - per_page (int, optional, default: 20)
   * - order_by (string, optional, default: date_created)
   * - order_dir (string, optional, default: desc)
   *   - allowed values for order_by: uid, name, email, verified, admin, date_created
   *   - allowed values for order_dir: asc, desc
   * - q (string, optional, default: "")
   *
   * Devuelve:
   * - 200: {
   *   message: "ok",
   *   pagination: {
   *     page: number,
   *     per_page: number,
   *     total: number,
   *     total_pages: number,
   *     order_by: string,
   *     order_dir: string,
   *     q: string
   *   },
   *   users: [
   *     {
   *       uid: string,
   *       name: string,
   *       email: string,
   *       avatar: string|null,
   *       verified: boolean,
   *       admin: boolean,
   *       master: boolean,
   *       date_created: string,
   *       banned: boolean
   *     }
   *   ]
   * }
   * - 401: { message: "No autorizado" }
   */
  public function userList() {
    $pageParam = $this->request->getVar('page');
    $perPageParam = $this->request->getVar('per_page');

    $page = (is_numeric($pageParam) && $pageParam !== '') ? (int) $pageParam : 1;
    $perPage = (is_numeric($perPageParam) && $perPageParam !== '') ? (int) $perPageParam : 20;

    $orderBy = (string) ($this->request->getVar('order_by') ?? 'date_created');
    $orderDir = (string) ($this->request->getVar('order_dir') ?? 'desc');

    $qParam = $this->request->getVar('q');
    $q = is_string($qParam) ? trim($qParam) : '';
    $perPage = min(255, max(1, $perPage));

    $allowedOrderBy = [
      'uid' => 'users.uid',
      'name' => 'users.name',
      'email' => 'users.email',
      'verified' => 'users.verified',
      'master' => 'users.master',
      'admin' => 'users.admin',
      'date_created' => 'users.date_created',
    ];

    $resolvedOrderBy = $allowedOrderBy[$orderBy] ?? $allowedOrderBy['date_created'];
    $resolvedOrderByField = array_search($resolvedOrderBy, $allowedOrderBy, true) ?: 'date_created';
    $resolvedOrderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

    $total = $this->userModel->getTotalUsers($q);
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

    $page = max(1, min($page, $totalPages));

    $offset = ($page - 1) * $perPage;
    $items = $this->userModel->getUsers($offset, $perPage, $resolvedOrderBy, $resolvedOrderDir, $q);

    $items = array_map(function ($user) {
      $user->avatar = build_avatar_url($user->avatar ?? null);
      $user->verified = (bool) ($user->verified ?? false);
      $user->master = (bool) ($user->master ?? false);
      $user->admin = (bool) ($user->admin ?? false);
      $user->banned = (bool) ($user->banned ?? false);
      return $user;
    }, $items);

    return $this->respond([
      'message' => 'ok',
      'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'order_by' => $resolvedOrderByField,
        'order_dir' => $resolvedOrderDir,
        'q' => $q,
      ],
      'users' => $items,
    ], 200);
  }

  /**
   * Endpoint: GET /v1/admin/user/{uid}
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - uid (path param, string)
   *
   * Devuelve:
   * - 200: {
   *   message: "ok",
   *   user: {
   *     uid: string,
   *     name: string,
   *     email: string,
   *     avatar: string|null,
   *     verified: boolean,
   *     admin: boolean,
   *     master: boolean,
   *     date_created: string,
   *     banned: boolean
   *   },
   *   bans: [
   *     {
   *       id: number,
   *       banned_by: string|null,
   *       banned_by_name: string|null,
   *       reason: string|null,
   *       permanent: boolean,
   *       date_start: string,
   *       date_end: string|null,
   *       active: boolean
   *     }
   *   ]
   * }
   * - 404: { message: "Usuario no encontrado" }
   * - 401: { message: "No autorizado" }
   */
  public function user(string $uid) {
    $user = $this->userModel->getUserForAdmin($uid);

    if ($user === null) {
      return $this->respond(['message' => 'Usuario no encontrado'], 404);
    }

    $user->avatar = build_avatar_url($user->avatar ?? null);
    $user->verified = (bool) ($user->verified ?? false);
    $user->master = (bool) ($user->master ?? false);
    $user->admin = (bool) ($user->admin ?? false);
    $user->banned = (bool) ($user->banned ?? false);

    $bans = $this->userModel->getUserBanHistory($uid);
    $bans = array_map(function ($ban) {
      $ban->permanent = (bool) ($ban->permanent ?? false);
      $ban->active = (bool) ($ban->active ?? false);
      return $ban;
    }, $bans);

    return $this->respond([
      'message' => 'ok',
      'user' => $user,
      'bans' => $bans,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/admin/ban-user
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - uid (string): UID del usuario a banear
   * - permanent (int|string|bool): 1 para ban permanente, 0 para temporal
   * - date_end (string|null): fecha fin para bans temporales (formato parseable por DateTime)
   * - reason (string): motivo del ban
   *
   * Devuelve:
   * - 200: {
   *   message: "ok",
   *   ban: {
   *     uid: string,
   *     banned_by: string,
   *     reason: string,
   *     permanent: boolean,
   *     date_start: string,
   *     date_end: string|null
   *   }
   * }
   * - 400: { message: "..." }
   * - 401: { message: "No autorizado" }
   * - 404: { message: "Usuario no encontrado" }
   * - 409: { message: "El usuario ya tiene un ban activo" }
   */
  public function banUser() {
    $uidParam = $this->request->getVar('uid');
    $permanentParam = $this->request->getVar('permanent');
    $dateEndParam = $this->request->getVar('date_end');
    $reasonParam = $this->request->getVar('reason');

    $uid = is_string($uidParam) ? trim($uidParam) : '';
    $reason = is_string($reasonParam) ? trim($reasonParam) : '';

    if ($uid === '') {
      return $this->respond(['message' => 'El UID del usuario es obligatorio'], 400);
    }

    if ($reason === '') {
      return $this->respond(['message' => 'El motivo del ban es obligatorio'], 400);
    }

    $normalizedPermanent = is_bool($permanentParam) ? (int) $permanentParam : (string) $permanentParam;

    if (!in_array((string) $normalizedPermanent, ['0', '1'], true)) {
      return $this->respond(['message' => 'El campo permanent debe ser 0 o 1'], 400);
    }

    $permanent = ((int) $normalizedPermanent) === 1;

    $user = $this->userModel->getUser($uid);

    if ($user === null) {
      return $this->respond(['message' => 'Usuario no encontrado'], 404);
    }

    if (user_is_banned($uid)) {
      return $this->respond(['message' => 'El usuario ya tiene un ban activo'], 409);
    }

    $bannedBy = $this->getUserUidFromJwt();

    if ($bannedBy === null || $bannedBy === '') {
      return $this->respond(['message' => 'No autorizado'], 401);
    }

    $dateStart = date('Y-m-d H:i:s');
    $dateEnd = null;

    if (!$permanent) {
      $rawDateEnd = is_string($dateEndParam) ? trim($dateEndParam) : '';

      if ($rawDateEnd === '') {
        return $this->respond(['message' => 'La fecha fin es obligatoria para bans temporales'], 400);
      }

      try {
        $dateEndObj = new \DateTimeImmutable($rawDateEnd);
      } catch (\Throwable $e) {
        return $this->respond(['message' => 'La fecha fin no tiene un formato valido'], 400);
      }

      $dateEnd = $dateEndObj->format('Y-m-d H:i:s');

      if ($dateEnd <= $dateStart) {
        return $this->respond(['message' => 'La fecha fin debe ser posterior a la fecha actual'], 400);
      }
    }

    $created = $this->userModel->createUserBan($uid, $bannedBy, $permanent, $dateEnd, $reason, $dateStart);

    if (!$created) {
      return $this->respond(['message' => 'No se pudo registrar el ban'], 500);
    }

    return $this->respond([
      'message' => 'ok',
      'ban' => [
        'uid' => $uid,
        'banned_by' => $bannedBy,
        'reason' => $reason,
        'permanent' => $permanent,
        'date_start' => $dateStart,
        'date_end' => $dateEnd,
      ],
    ], 200);
  }

  /**
   * Endpoint: GET /v1/admin/unban/{uid}
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - uid (path param, string): UID del usuario a desbanear
   *
   * Devuelve:
   * - 200: { message: "ok", uid: string, lifted: number }
   * - 400: { message: "El usuario no tiene bans activos" }
   * - 404: { message: "Usuario no encontrado" }
   * - 401: { message: "No autorizado" }
   */
  public function unbanUser(string $uid) {
    $user = $this->userModel->getUser($uid);

    if ($user === null) {
      return $this->respond(['message' => 'Usuario no encontrado'], 404);
    }

    if (!user_is_banned($uid)) {
      return $this->respond(['message' => 'El usuario no tiene bans activos'], 400);
    }

    $lifted = $this->userModel->liftActiveBans($uid);

    return $this->respond([
      'message' => 'ok',
      'uid' => $uid,
      'lifted' => $lifted,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/admin/toggle-admin
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - uid (string): UID del usuario
   * - state (int|string): 1 para activar admin, 0 para desactivar
   *
   * Devuelve:
   * - 200: { message: "ok", uid: string, admin: boolean }
   * - 400: { message: "..." }
   * - 403: { message: "No puedes modificar tus propios permisos de admin" }
   * - 404: { message: "Usuario no encontrado" }
   * - 401: { message: "No autorizado" }
   */
  public function toggleAdmin() {
    return $this->toggleUserFlag('admin');
  }

  /**
   * Endpoint: POST /v1/admin/toggle-master
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - uid (string): UID del usuario
   * - state (int|string): 1 para activar master, 0 para desactivar
   *
   * Devuelve:
   * - 200: { message: "ok", uid: string, master: boolean }
   * - 400: { message: "..." }
   * - 404: { message: "Usuario no encontrado" }
   * - 401: { message: "No autorizado" }
   */
  public function toggleMaster() {
    return $this->toggleUserFlag('master');
  }

  private function toggleUserFlag(string $field) {
    $uidParam = $this->request->getVar('uid');
    $stateParam = $this->request->getVar('state');
    $currentUserUid = $this->getUserUidFromJwt();

    $uid = is_string($uidParam) ? trim($uidParam) : '';

    if ($uid === '') {
      return $this->respond([
        'message' => 'El UID del usuario es obligatorio',
      ], 400);
    }

    $normalizedState = is_bool($stateParam) ? (int) $stateParam : (string) $stateParam;

    if (!in_array((string) $normalizedState, ['0', '1'], true)) {
      return $this->respond([
        'message' => 'El estado debe ser 0 o 1',
      ], 400);
    }

    $state = (int) $normalizedState;

    $user = $this->userModel->getUser($uid);

    if ($user === null) {
      return $this->respond([
        'message' => 'Usuario no encontrado',
      ], 404);
    }

    if ($field === 'admin' && $currentUserUid !== null && $currentUserUid === $uid) {
      return $this->respond([
        'message' => 'No puedes modificar tus propios permisos de admin',
      ], 403);
    }

    if ($state === 1 && !((bool) ($user->verified ?? false))) {
      return $this->respond([
        'message' => 'Un usuario no verificado no puede volverse admin ni master',
      ], 400);
    }

    $success = $this->userModel->update($uid, [$field => $state]);

    if (!$success) {
      return $this->respond([
        'message' => 'No se pudo actualizar el usuario',
      ], 500);
    }

    return $this->respond([
      'message' => 'ok',
      'uid' => $uid,
      $field => (bool) $state,
    ], 200);
  }
}
