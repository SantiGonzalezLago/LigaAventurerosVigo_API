<?php

namespace App\Controllers\V1\Admin;

use App\Controllers\V1\BaseApiController;

class Admin extends BaseApiController {

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
}