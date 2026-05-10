<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSpeciesModel extends Model {

  protected $table = 'system_species';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'system_id',
    'name',
    'active',
  ];

  public function getBySystemId(int $systemId): array {
    return $this->db->table($this->table)
      ->select('id, system_id, name, active')
      ->where('system_id', $systemId)
      ->orderBy('id', 'asc')
      ->get()
      ->getResult();
  }

  public function addSpecies(int $systemId, string $name, int $active = 1): int {
    $this->db->table($this->table)->insert([
      'system_id' => $systemId,
      'name' => $name,
      'active' => $active,
    ]);

    return (int) $this->db->insertID();
  }

  public function updateSpecies(int $systemId, int $id, array $data): bool {
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

  public function deleteSpecies(int $systemId, int $id): bool {
    $this->db->table($this->table)
      ->where('id', $id)
      ->where('system_id', $systemId)
      ->delete();

    return $this->db->affectedRows() > 0;
  }

}
