<?php

namespace App\Controllers\V1;

use App\Models\SystemModel;
use App\Models\SystemSettingModel;
use App\Models\SystemSpeciesModel;
use App\Models\SystemClassModel;
use App\Models\SystemTierModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class GameSystems extends BaseApiController {
  protected SystemModel $gameSystemModel;
  protected SystemTierModel $systemTierModel;
  protected SystemSettingModel $systemSettingModel;
  protected SystemSpeciesModel $systemSpeciesModel;
  protected SystemClassModel $systemClassModel;

  public function __construct() {
    $this->gameSystemModel = new SystemModel();
    $this->systemTierModel = new SystemTierModel();
    $this->systemSettingModel = new SystemSettingModel();
    $this->systemSpeciesModel = new SystemSpeciesModel();
    $this->systemClassModel = new SystemClassModel();
  }

  /**
   * Endpoint: GET /v1/game-systems
   *
   * Devuelve:
   * - 200: { message: "ok", game_systems: [{ id: int, name: string, slug: string, icon: string|null, pc_limit: int, active: bool }] }
   */
  public function index() {
    $systems = $this->gameSystemModel->getAll();

    $systems = array_map(static function (object $system): object {
      if (!empty($system->icon)) {
        $system->icon = rtrim(base_url(), '/') . '/images/system/' . ltrim($system->icon, '/');
      }

      $system->pc_limit = (int) $system->pc_limit;
      $system->active = (bool) $system->active;

      return $system;
    }, $systems);

    return $this->respond([
      'message' => 'ok',
      'game_systems' => $systems,
    ], 200);
  }

  /**
   * Endpoint: GET /v1/game-systems/{slug}
   *
   * Devuelve:
   * - 200: { message: "ok", game_system: { id: int, name: string, slug: string, icon: string|null, pc_limit: int, active: bool } }
   * - 404: { message: "Sistema no encontrado" }
   */
  public function show(string $slug) {
    $normalizedSlug = trim($slug);

    if ($normalizedSlug === '') {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $system = $this->gameSystemModel->getBySlug($normalizedSlug);

    if ($system === null) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    if (!empty($system->icon)) {
      $system->icon = rtrim(base_url(), '/') . '/images/system/' . ltrim($system->icon, '/');
    }

    $system->id = (int) $system->id;
    $system->pc_limit = (int) $system->pc_limit;
    $system->active = (bool) $system->active;

    return $this->respond([
      'message' => 'ok',
      'game_system' => $system,
    ], 200);
  }

  /**
   * Endpoint: GET /v1/game-systems/{id}/tiers
   *
   * Devuelve:
   * - 200: { message: "ok", tiers: [{ id: int, system_id: int, name: string, min_level: int, max_level: int, active: bool }] }
   * - 404: { message: "Sistema no encontrado" }
   */
  public function tiers(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $tiers = $this->systemTierModel->getBySystemId($id);

    $tiers = array_map(static function (object $tier): object {
      $tier->id = (int) $tier->id;
      $tier->system_id = (int) $tier->system_id;
      $tier->min_level = (int) $tier->min_level;
      $tier->max_level = (int) $tier->max_level;
      $tier->active = (bool) $tier->active;

      return $tier;
    }, $tiers);

    return $this->respond([
      'message' => 'ok',
      'tiers' => $tiers,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/tiers/update
   *
   * Recibe:
   * - Authorization: Bearer <jwt> (admin)
   * - tiers (array): [{ id?: int, name: string, min_level: int, max_level: int, active?: bool }]
   *
   * Todos los tiers se sincronizan en bloque sin perder ids relacionales:
   * - se actualizan los que llegan con id
   * - se crean los que llegan sin id
   * - se eliminan los que no llegan
   *
   * La continuidad de niveles se valida solo con tiers activos.
   *
   * Devuelve:
   * - 200: { message: "ok", tiers: [...] }
   * - 400: { message: "..." }
   * - 404: { message: "Sistema no encontrado" }
   * - 401: { message: "No autorizado" }
   */
  public function updateTiers(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $tiersPayload = $this->request->getJSON(true);

    if (is_array($tiersPayload) && array_key_exists('tiers', $tiersPayload)) {
      $tiersInput = $tiersPayload['tiers'];
    } else {
      $tiersInput = $this->request->getVar('tiers');
    }

    if (is_string($tiersInput)) {
      $decodedTiers = json_decode($tiersInput, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->respond(['message' => 'El campo tiers debe ser un JSON válido'], 400);
      }

      $tiersInput = $decodedTiers;
    }

    if (!is_array($tiersInput)) {
      return $this->respond(['message' => 'El campo tiers es obligatorio y debe ser un array'], 400);
    }

    $normalizedTiers = [];

    foreach ($tiersInput as $index => $tier) {
      if (!is_array($tier)) {
        return $this->respond(['message' => 'Cada tier debe ser un objeto válido'], 400);
      }

      $tierId = null;

      if (array_key_exists('id', $tier) && $tier['id'] !== null && $tier['id'] !== '') {
        $tierId = (int) $tier['id'];

        if ($tierId < 1) {
          return $this->respond(['message' => 'Si se envía id, debe ser un entero mayor o igual a 1'], 400);
        }
      }

      $name = isset($tier['name']) ? trim((string) $tier['name']) : '';
      $minLevel = isset($tier['min_level']) ? (int) $tier['min_level'] : null;
      $maxLevel = isset($tier['max_level']) ? (int) $tier['max_level'] : null;
      $active = array_key_exists('active', $tier) ? ((bool) $tier['active'] ? 1 : 0) : 1;

      if ($name === '') {
        return $this->respond(['message' => 'El nombre es obligatorio en todos los tiers'], 400);
      }

      if ($minLevel === null || $maxLevel === null) {
        return $this->respond(['message' => 'Todos los tiers deben incluir min_level y max_level'], 400);
      }

      if ($minLevel < 1 || $maxLevel < 1) {
        return $this->respond(['message' => 'Los niveles deben ser mayores o iguales a 1'], 400);
      }

      if ($minLevel > $maxLevel) {
        return $this->respond(['message' => 'min_level no puede ser mayor que max_level'], 400);
      }

      $normalizedTiers[] = [
        'id' => $tierId,
        'name' => $name,
        'min_level' => $minLevel,
        'max_level' => $maxLevel,
        'active' => $active,
        '_index' => $index,
      ];
    }

    $tiersForContinuity = array_values(array_filter($normalizedTiers, static function (array $tier): bool {
      return $tier['active'] === 1;
    }));

    usort($tiersForContinuity, static function (array $a, array $b): int {
      if ($a['min_level'] === $b['min_level']) {
        return $a['_index'] <=> $b['_index'];
      }

      return $a['min_level'] <=> $b['min_level'];
    });

    $expectedMinLevel = 1;

    foreach ($tiersForContinuity as $tier) {
      if ($tier['min_level'] !== $expectedMinLevel) {
        return $this->respond([
          'message' => 'Los tiers deben formar un sistema continuo sin huecos ni solapes (ej: 1-4, 5-8)',
        ], 400);
      }

      $expectedMinLevel = $tier['max_level'] + 1;
    }

    $existingTiers = $this->systemTierModel->getBySystemId($id);
    $existingTierById = [];

    foreach ($existingTiers as $existingTier) {
      $existingTierById[(int) $existingTier->id] = $existingTier;
    }

    $seenIncomingIds = [];
    $tiersToUpdate = [];
    $tiersToInsert = [];

    foreach ($normalizedTiers as $tier) {
      $incomingId = $tier['id'];

      if ($incomingId !== null) {
        if (isset($seenIncomingIds[$incomingId])) {
          return $this->respond(['message' => 'No se puede repetir el mismo id de tier en la misma petición'], 400);
        }

        if (!isset($existingTierById[$incomingId])) {
          return $this->respond(['message' => 'Hay tiers con id inexistente o que no pertenecen al sistema'], 400);
        }

        $seenIncomingIds[$incomingId] = true;
        $tiersToUpdate[] = [
          'id' => $incomingId,
          'name' => $tier['name'],
          'min_level' => $tier['min_level'],
          'max_level' => $tier['max_level'],
          'active' => $tier['active'],
        ];

        continue;
      }

      $tiersToInsert[] = [
        'name' => $tier['name'],
        'min_level' => $tier['min_level'],
        'max_level' => $tier['max_level'],
        'active' => $tier['active'],
      ];
    }

    $tierIdsToDelete = [];

    foreach ($existingTierById as $existingId => $existingTier) {
      if (!isset($seenIncomingIds[$existingId])) {
        $tierIdsToDelete[] = $existingId;
      }
    }

    $saved = $this->systemTierModel->syncBySystemId($id, $tiersToUpdate, $tiersToInsert, $tierIdsToDelete);

    if (!$saved) {
      return $this->respond(['message' => 'No se pudieron actualizar los tiers'], 500);
    }

    $tiers = $this->systemTierModel->getBySystemId($id);

    $tiers = array_map(static function (object $tier): object {
      $tier->id = (int) $tier->id;
      $tier->system_id = (int) $tier->system_id;
      $tier->min_level = (int) $tier->min_level;
      $tier->max_level = (int) $tier->max_level;
      $tier->active = (bool) $tier->active;

      return $tier;
    }, $tiers);

    return $this->respond([
      'message' => 'ok',
      'tiers' => $tiers,
    ], 200);
  }

  /**
   * Endpoint: GET /v1/game-systems/{id}/setting
   *
   * Devuelve:
   * - 200: { message: "ok", settings: [{ id: int, system_id: int, name: string, slug: string, description: string|null, active: bool }] }
   * - 404: { message: "Sistema no encontrado" }
   */
  public function settings(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $settings = $this->systemSettingModel->getBySystemId($id);

    $settings = array_map(static function (object $setting): object {
      $setting->id = (int) $setting->id;
      $setting->system_id = (int) $setting->system_id;
      $setting->active = (bool) $setting->active;

      return $setting;
    }, $settings);

    return $this->respond([
      'message' => 'ok',
      'settings' => $settings,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/setting/add
   *
   * Recibe:
   * - Authorization: Bearer <jwt> (admin)
   * - name (string): nombre del setting
   * - slug (string): slug único por sistema
   * - description (string, opcional): descripción del setting
   * - active (bool, opcional): si el setting está activo (por defecto true)
   *
   * Devuelve:
   * - 201: { message: "ok", id: int }
   * - 400: { message: "..." }
   * - 404: { message: "Sistema no encontrado" }
   * - 409: { message: "El slug ya existe en este sistema" }
   * - 401: { message: "No autorizado" }
   */
  public function addSetting(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $nameParam = $this->request->getVar('name');
    $slugParam = $this->request->getVar('slug');
    $descriptionParam = $this->request->getVar('description');
    $activeParam = $this->request->getVar('active');

    $name = is_string($nameParam) ? trim($nameParam) : '';
    $slug = is_string($slugParam) ? trim($slugParam) : '';
    $description = $descriptionParam !== null ? trim((string) $descriptionParam) : null;
    $active = $activeParam !== null ? ((bool) $activeParam ? 1 : 0) : 1;

    if ($name === '') {
      return $this->respond(['message' => 'El nombre es obligatorio'], 400);
    }

    if ($slug === '') {
      return $this->respond(['message' => 'El slug es obligatorio'], 400);
    }

    if ($this->systemSettingModel->slugExists($id, $slug)) {
      return $this->respond(['message' => 'El slug ya existe en este sistema'], 409);
    }

    if ($description === '') {
      $description = null;
    }

    $newId = $this->systemSettingModel->addSetting($id, $name, $slug, $description, $active);

    return $this->respond([
      'message' => 'ok',
      'id' => $newId,
    ], 201);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/setting/{settingId}/update
   *
   * Recibe:
   * - Authorization: Bearer <jwt> (admin)
   * - name (string, opcional): nuevo nombre
   * - slug (string, opcional): nuevo slug único por sistema
   * - description (string, opcional): nueva descripción
   * - active (bool, opcional): si el setting está activo
   *
   * Devuelve:
   * - 200: { message: "ok" }
   * - 400: { message: "..." }
   * - 404: { message: "Sistema no encontrado" | "Setting no encontrado" }
   * - 409: { message: "El slug ya existe en este sistema" }
   * - 401: { message: "No autorizado" }
   */
  public function updateSetting(int $id, int $settingId) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $nameParam = $this->request->getVar('name');
    $slugParam = $this->request->getVar('slug');
    $descriptionParam = $this->request->getVar('description');
    $activeParam = $this->request->getVar('active');

    $data = [];

    if ($nameParam !== null) {
      $name = trim((string) $nameParam);

      if ($name === '') {
        return $this->respond(['message' => 'El nombre no puede estar vacío'], 400);
      }

      $data['name'] = $name;
    }

    if ($slugParam !== null) {
      $slug = trim((string) $slugParam);

      if ($slug === '') {
        return $this->respond(['message' => 'El slug no puede estar vacío'], 400);
      }

      if ($this->systemSettingModel->slugExists($id, $slug, $settingId)) {
        return $this->respond(['message' => 'El slug ya existe en este sistema'], 409);
      }

      $data['slug'] = $slug;
    }

    if ($descriptionParam !== null) {
      $description = trim((string) $descriptionParam);
      $data['description'] = $description !== '' ? $description : null;
    }

    if ($activeParam !== null) {
      $data['active'] = (bool) $activeParam ? 1 : 0;
    }

    if (empty($data)) {
      return $this->respond(['message' => 'No se han enviado campos para actualizar'], 400);
    }

    $updated = $this->systemSettingModel->updateSetting($id, $settingId, $data);

    if (!$updated) {
      return $this->respond(['message' => 'Setting no encontrado'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  /**
   * Endpoint: DELETE /v1/game-systems/{id}/setting/{settingId}/delete
   *
   * Recibe:
   * - Authorization: Bearer <jwt> (admin)
   *
   * Devuelve:
   * - 200: { message: "ok" }
   * - 404: { message: "Sistema no encontrado" | "Setting no encontrado" }
   * - 401: { message: "No autorizado" }
   */
  public function deleteSetting(int $id, int $settingId) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $deleted = $this->systemSettingModel->deleteSetting($id, $settingId);

    if (!$deleted) {
      return $this->respond(['message' => 'Setting no encontrado'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  /**
   * Endpoint: GET /v1/game-systems/{id}/species
    *
    * Devuelve:
    * - 200: { message: "ok", species: [{ id: int, system_id: int, name: string, active: bool }] }
    * - 404: { message: "Sistema no encontrado" }
   */
  public function species(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $species = $this->systemSpeciesModel->getBySystemId($id);

    $species = array_map(static function (object $specie): object {
      $specie->id = (int) $specie->id;
      $specie->system_id = (int) $specie->system_id;
      $specie->active = (bool) $specie->active;

      return $specie;
    }, $species);

    return $this->respond([
      'message' => 'ok',
      'species' => $species,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/species/add
    *
    * Recibe:
    * - Authorization: Bearer <jwt> (admin)
    * - name (string): nombre de la especie
    * - active (bool, opcional): si la especie está activa (por defecto true)
    *
    * Devuelve:
    * - 201: { message: "ok", id: int }
    * - 400: { message: "..." }
    * - 404: { message: "Sistema no encontrado" }
    * - 401: { message: "No autorizado" }
   */
  public function addSpecies(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $nameParam = $this->request->getVar('name');
    $activeParam = $this->request->getVar('active');

    $name = is_string($nameParam) ? trim($nameParam) : '';
    $active = $activeParam !== null ? ((bool) $activeParam ? 1 : 0) : 1;

    if ($name === '') {
      return $this->respond(['message' => 'El nombre es obligatorio'], 400);
    }

    $newId = $this->systemSpeciesModel->addSpecies($id, $name, $active);

    return $this->respond([
      'message' => 'ok',
      'id' => $newId,
    ], 201);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/species/{speciesId}/update
    *
    * Recibe:
    * - Authorization: Bearer <jwt> (admin)
    * - name (string, opcional): nuevo nombre
    * - active (bool, opcional): si la especie está activa
    *
    * Devuelve:
    * - 200: { message: "ok" }
    * - 400: { message: "..." }
    * - 404: { message: "Sistema no encontrado" | "Especie no encontrada" }
    * - 401: { message: "No autorizado" }
   */
  public function updateSpecies(int $id, int $speciesId) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $nameParam = $this->request->getVar('name');
    $activeParam = $this->request->getVar('active');

    $data = [];

    if ($nameParam !== null) {
      $name = trim((string) $nameParam);

      if ($name === '') {
        return $this->respond(['message' => 'El nombre no puede estar vacío'], 400);
      }

      $data['name'] = $name;
    }

    if ($activeParam !== null) {
      $data['active'] = (bool) $activeParam ? 1 : 0;
    }

    if (empty($data)) {
      return $this->respond(['message' => 'No se han enviado campos para actualizar'], 400);
    }

    $updated = $this->systemSpeciesModel->updateSpecies($id, $speciesId, $data);

    if (!$updated) {
      return $this->respond(['message' => 'Especie no encontrada'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  /**
   * Endpoint: DELETE /v1/game-systems/{id}/species/{speciesId}/delete
    *
    * Recibe:
    * - Authorization: Bearer <jwt> (admin)
    *
    * Devuelve:
    * - 200: { message: "ok" }
    * - 404: { message: "Sistema no encontrado" | "Especie no encontrada" }
    * - 401: { message: "No autorizado" }
   */
  public function deleteSpecies(int $id, int $speciesId) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $deleted = $this->systemSpeciesModel->deleteSpecies($id, $speciesId);

    if (!$deleted) {
      return $this->respond(['message' => 'Especie no encontrada'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  /**
   * Endpoint: GET /v1/game-systems/{id}/class
    *
    * Devuelve:
    * - 200: { message: "ok", classes: [{ id: int, system_id: int, name: string, active: bool }] }
    * - 404: { message: "Sistema no encontrado" }
   */
  public function classes(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $classes = $this->systemClassModel->getBySystemId($id);

    $classes = array_map(static function (object $class): object {
      $class->id = (int) $class->id;
      $class->system_id = (int) $class->system_id;
      $class->active = (bool) $class->active;

      return $class;
    }, $classes);

    return $this->respond([
      'message' => 'ok',
      'classes' => $classes,
    ], 200);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/class/add
    *
    * Recibe:
    * - Authorization: Bearer <jwt> (admin)
    * - name (string): nombre de la clase
    * - active (bool, opcional): si la clase está activa (por defecto true)
    *
    * Devuelve:
    * - 201: { message: "ok", id: int }
    * - 400: { message: "..." }
    * - 404: { message: "Sistema no encontrado" }
    * - 401: { message: "No autorizado" }
   */
  public function addClass(int $id) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $nameParam = $this->request->getVar('name');
    $activeParam = $this->request->getVar('active');

    $name = is_string($nameParam) ? trim($nameParam) : '';
    $active = $activeParam !== null ? ((bool) $activeParam ? 1 : 0) : 1;

    if ($name === '') {
      return $this->respond(['message' => 'El nombre es obligatorio'], 400);
    }

    $newId = $this->systemClassModel->addClass($id, $name, $active);

    return $this->respond([
      'message' => 'ok',
      'id' => $newId,
    ], 201);
  }

  /**
   * Endpoint: POST /v1/game-systems/{id}/class/{classId}/update
    *
    * Recibe:
    * - Authorization: Bearer <jwt> (admin)
    * - name (string, opcional): nuevo nombre
    * - active (bool, opcional): si la clase está activa
    *
    * Devuelve:
    * - 200: { message: "ok" }
    * - 400: { message: "..." }
    * - 404: { message: "Sistema no encontrado" | "Clase no encontrada" }
    * - 401: { message: "No autorizado" }
   */
  public function updateClass(int $id, int $classId) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $nameParam = $this->request->getVar('name');
    $activeParam = $this->request->getVar('active');

    $data = [];

    if ($nameParam !== null) {
      $name = trim((string) $nameParam);

      if ($name === '') {
        return $this->respond(['message' => 'El nombre no puede estar vacío'], 400);
      }

      $data['name'] = $name;
    }

    if ($activeParam !== null) {
      $data['active'] = (bool) $activeParam ? 1 : 0;
    }

    if (empty($data)) {
      return $this->respond(['message' => 'No se han enviado campos para actualizar'], 400);
    }

    $updated = $this->systemClassModel->updateClass($id, $classId, $data);

    if (!$updated) {
      return $this->respond(['message' => 'Clase no encontrada'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  /**
   * Endpoint: DELETE /v1/game-systems/{id}/class/{classId}/delete
    *
    * Recibe:
    * - Authorization: Bearer <jwt> (admin)
    *
    * Devuelve:
    * - 200: { message: "ok" }
    * - 404: { message: "Sistema no encontrado" | "Clase no encontrada" }
    * - 401: { message: "No autorizado" }
   */
  public function deleteClass(int $id, int $classId) {
    if (!$this->gameSystemModel->existsById($id)) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    $deleted = $this->systemClassModel->deleteClass($id, $classId);

    if (!$deleted) {
      return $this->respond(['message' => 'Clase no encontrada'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  /**
   * Endpoint: POST /v1/game-systems/add
   *
   * Recibe:
   * - Authorization: Bearer <jwt> (admin)
   * - name (string): nombre del sistema
   * - slug (string): slug único del sistema
   * - icon (file, opcional): imagen del icono
   * - pc_limit (int, opcional): límite de personajes por jugador (por defecto 0, ilimitado)
   * - active (bool, opcional): si el sistema está activo (por defecto false)
   *
   * Devuelve:
   * - 201: { message: "ok", id: int }
   * - 400: { message: "..." }
   * - 409: { message: "El slug ya existe" }
   * - 401: { message: "No autorizado" }
   */
  public function add() {
    $nameParam    = $this->request->getVar('name');
    $slugParam    = $this->request->getVar('slug');
    $activeParam  = $this->request->getVar('active');
    $pcLimitParam = $this->request->getVar('pc_limit');
    $iconFile     = $this->request->getFile('icon');

    $name     = is_string($nameParam) ? trim($nameParam) : '';
    $slug     = is_string($slugParam) ? trim($slugParam) : '';
    $active   = $activeParam !== null ? (bool) $activeParam : false;
    $pcLimit  = $pcLimitParam !== null ? max(0, (int) $pcLimitParam) : 0;
    $hasIcon  = $iconFile !== null && $iconFile->getError() !== UPLOAD_ERR_NO_FILE;

    if ($name === '') {
      return $this->respond(['message' => 'El nombre es obligatorio'], 400);
    }

    if ($slug === '') {
      return $this->respond(['message' => 'El slug es obligatorio'], 400);
    }

    if ($this->gameSystemModel->slugExists($slug)) {
      return $this->respond(['message' => 'El slug ya existe'], 409);
    }

    $iconFilename = null;

    if ($hasIcon) {
      if (!$iconFile->isValid()) {
        return $this->respond(['message' => 'El icono enviado no es válido'], 400);
      }

      $admin = $this->getUserFromJwt();
      $iconFilename = $this->storeSystemIcon($slug, $admin?->uid ?? 'system', $iconFile);

      if ($iconFilename === null) {
        return $this->respond(['message' => 'No se pudo procesar el icono'], 400);
      }
    }

    $id = $this->gameSystemModel->addSystem($name, $slug, $iconFilename, $pcLimit, $active);

    return $this->respond([
      'message' => 'ok',
      'id' => $id,
    ], 201);
  }

  /**
   * Endpoint: POST /v1/game-systems/update/{id}
   *
   * Recibe:
   * - Authorization: Bearer <jwt> (admin)
   * - name (string, opcional): nuevo nombre
   * - slug (string, opcional): nuevo slug
   * - icon (file, opcional): nueva imagen del icono
   * - remove_icon (any, opcional): si se envía, elimina el icono actual
   * - pc_limit (int, opcional): límite de personajes por jugador
   * - active (bool, opcional): si el sistema está activo o no
   *
   * Devuelve:
   * - 200: { message: "ok" }
   * - 400: { message: "..." }
   * - 404: { message: "Sistema no encontrado" }
   * - 409: { message: "El slug ya existe" }
   * - 401: { message: "No autorizado" }
   */
  public function update(int $id) {
    $nameParam    = $this->request->getVar('name');
    $slugParam    = $this->request->getVar('slug');
    $activeParam  = $this->request->getVar('active');
    $pcLimitParam = $this->request->getVar('pc_limit');
    $iconFile     = $this->request->getFile('icon');
    $removeIcon   = $this->request->getVar('remove_icon');

    $hasIcon = $iconFile !== null && $iconFile->getError() !== UPLOAD_ERR_NO_FILE;

    $data = [];

    if ($nameParam !== null) {
      $name = trim((string) $nameParam);

      if ($name === '') {
        return $this->respond(['message' => 'El nombre no puede estar vacío'], 400);
      }

      $data['name'] = $name;
    }

    $resolvedSlug = $slugParam !== null ? trim((string) $slugParam) : null;

    if ($resolvedSlug !== null) {
      if ($resolvedSlug === '') {
        return $this->respond(['message' => 'El slug no puede estar vacío'], 400);
      }

      if ($this->gameSystemModel->slugExists($resolvedSlug, $id)) {
        return $this->respond(['message' => 'El slug ya existe'], 409);
      }

      $data['slug'] = $resolvedSlug;
    }

    if ($hasIcon) {
      if (!$iconFile->isValid()) {
        return $this->respond(['message' => 'El icono enviado no es válido'], 400);
      }

      $admin = $this->getUserFromJwt();
      $slugForFilename = $resolvedSlug ?? (string) $id;
      $iconFilename = $this->storeSystemIcon($slugForFilename, $admin?->uid ?? 'system', $iconFile);

      if ($iconFilename === null) {
        return $this->respond(['message' => 'No se pudo procesar el icono'], 400);
      }

      $data['icon'] = $iconFilename;
    } elseif ($removeIcon !== null) {
      $data['icon'] = null;
    }

    if ($pcLimitParam !== null) {
      $data['pc_limit'] = max(0, (int) $pcLimitParam);
    }

    if ($activeParam !== null) {
      $data['active'] = (bool) $activeParam ? 1 : 0;
    }

    if (empty($data)) {
      return $this->respond(['message' => 'No se han enviado campos para actualizar'], 400);
    }

    $updated = $this->gameSystemModel->updateSystem($id, $data);

    if (!$updated) {
      return $this->respond(['message' => 'Sistema no encontrado'], 404);
    }

    return $this->respond(['message' => 'ok'], 200);
  }

  private function storeSystemIcon(string $slug, string $uploaderUid, UploadedFile $iconFile): ?string {
    $allowedMimeTypes = [
      'image/jpeg' => 'jpg',
      'image/jpg'  => 'jpg',
      'image/png'  => 'png',
      'image/webp' => 'webp',
      'image/gif'  => 'gif',
    ];

    $mimeType = strtolower((string) $iconFile->getMimeType());

    if (!isset($allowedMimeTypes[$mimeType])) {
      return null;
    }

    $iconDirectory = FCPATH . 'images/system/';
    $relativeDirectory = 'images/system';

    if (!is_dir($iconDirectory) && !mkdir($iconDirectory, 0755, true) && !is_dir($iconDirectory)) {
      return null;
    }

    $extension = $allowedMimeTypes[$mimeType];
    $filename = $slug . '_' . date('YmdHis') . '.' . $extension;
    $targetPath = $iconDirectory . $filename;

    try {
      $iconFile->move($iconDirectory, $filename, true);
    } catch (\Throwable $e) {
      return null;
    }

    $imageInfo = @getimagesize($targetPath);

    if ($imageInfo === false) {
      @unlink($targetPath);
      return null;
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);

    if ($width > 128 || $height > 128) {
      $masterDimension = $width >= $height ? 'width' : 'height';

      try {
        service('image')
          ->withFile($targetPath)
          ->resize(128, 128, true, $masterDimension)
          ->save($targetPath);
      } catch (\Throwable $e) {
        @unlink($targetPath);
        return null;
      }
    }

    if (!upload_log_create($uploaderUid, $relativeDirectory, $filename)) {
      @unlink($targetPath);
      return null;
    }

    return $filename;
  }
}
