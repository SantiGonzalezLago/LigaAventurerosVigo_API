<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSettingModel extends Model {

  protected $table = 'system_setting';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'system_id',
    'name',
    'slug',
    'description',
    'active',
  ];

  public function getBySystemId(int $systemId): array {
    return $this->db->table($this->table)
      ->select('id, system_id, name, slug, description, active')
      ->where('system_id', $systemId)
      ->orderBy('id', 'asc')
      ->get()
      ->getResult();
  }

  public function slugExists(int $systemId, string $slug, ?int $excludeId = null): bool {
    $builder = $this->db->table($this->table)
      ->where('system_id', $systemId)
      ->where('slug', $slug);

    if ($excludeId !== null) {
      $builder->where('id !=', $excludeId);
    }

    return $builder->countAllResults() > 0;
  }

  public function addSetting(int $systemId, string $name, string $slug, ?string $description, int $active = 1): int {
    $this->db->table($this->table)->insert([
      'system_id' => $systemId,
      'name' => $name,
      'slug' => $slug,
      'description' => $description,
      'active' => $active,
    ]);

    return (int) $this->db->insertID();
  }

  public function updateSetting(int $systemId, int $id, array $data): bool {
    $exists = $this->db->table($this->table)
      ->where('id', $id)
      ->where('system_id', $systemId)
      ->countAllResults() > 0;

    if (!$exists) {
      return false;
    }

    return (bool) $this->db->table($this->table)
      ->where('id', $id)
      ->where('system_id', $systemId)
      ->set($data)
      ->update();
  }

  public function deleteSetting(int $systemId, int $id): bool {
    $this->db->table($this->table)
      ->where('id', $id)
      ->where('system_id', $systemId)
      ->delete();

    return $this->db->affectedRows() > 0;
  }

}
