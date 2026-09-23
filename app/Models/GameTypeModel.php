<?php

namespace App\Models;

use CodeIgniter\Model;

class GameTypeModel extends Model {

  protected $table = 'game_type';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'name',
    'active',
  ];

  public function getAll(): array {
    return $this->db->table($this->table)
      ->select('id, name, active')
      ->orderBy('id', 'asc')
      ->get()
      ->getResult();
  }

  public function addType(string $name, int $active = 1): int {
    $this->db->table($this->table)->insert([
      'name' => $name,
      'active' => $active,
    ]);

    return (int) $this->db->insertID();
  }

  public function updateType(int $id, array $data): bool {
    $exists = $this->db->table($this->table)
      ->where('id', $id)
      ->countAllResults() > 0;

    if (!$exists) {
      return false;
    }

    return (bool) $this->db->table($this->table)
      ->where('id', $id)
      ->set($data)
      ->update();
  }

  public function deleteType(int $id): bool {
    $this->db->table($this->table)
      ->where('id', $id)
      ->delete();

    return $this->db->affectedRows() > 0;
  }

}