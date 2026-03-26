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
}
