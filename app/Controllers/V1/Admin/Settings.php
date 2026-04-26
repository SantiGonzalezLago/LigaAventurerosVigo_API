<?php

namespace App\Controllers\V1\Admin;

use App\Controllers\V1\BaseApiController;
use App\Models\SettingsModel;

class Settings extends BaseApiController {
  protected SettingsModel $settingsModel;

  public function __construct() {
    $this->settingsModel = new SettingsModel();
  }

  /**
   * Endpoint: GET /v1/admin/settings/get
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   *
   * Devuelve:
   * - 200: { message: "ok", settings: [{ key: string, description: string|null, value: string }] }
   * - 401: { message: "No autorizado" }
   */
  public function settingsGet() {
    $settings = $this->settingsModel->getAllSettings();

    return $this->respond([
      'message' => 'ok',
      'settings' => $settings,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/admin/settings/update
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - key (string): clave de la setting a actualizar
   * - value (string): nuevo valor
   *
   * Devuelve:
   * - 200: { message: "ok", key: string, value: string }
   * - 400: { message: "..." }
   * - 404: { message: "Setting no encontrada" }
   * - 401: { message: "No autorizado" }
   */
  public function settingsUpdate() {
    $keyParam = $this->request->getVar('key');
    $valueParam = $this->request->getVar('value');

    $key = is_string($keyParam) ? trim($keyParam) : '';

    if ($key === '') {
      return $this->respond(['message' => 'La clave es obligatoria'], 400);
    }

    if ($valueParam === null) {
      return $this->respond(['message' => 'El valor es obligatorio'], 400);
    }

    $value = (string) $valueParam;

    $updated = $this->settingsModel->updateSetting($key, $value);

    if (!$updated) {
      return $this->respond(['message' => 'Setting no encontrada'], 404);
    }

    return $this->respond([
      'message' => 'ok',
      'key' => $key,
      'value' => $value,
    ], 200);
  }
}