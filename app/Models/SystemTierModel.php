<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemTierModel extends Model {

  protected $table = 'system_tier';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'system_id',
    'name',
    'min_level',
    'max_level',
    'active',
  ];

  public function getBySystemId(int $systemId): array {
    return $this->db->table($this->table)
      ->select('id, system_id, name, min_level, max_level, active')
      ->where('system_id', $systemId)
      ->orderBy('min_level', 'asc')
      ->orderBy('id', 'asc')
      ->get()
      ->getResult();
  }

  public function syncBySystemId(int $systemId, array $tiersToUpdate, array $tiersToInsert, array $tierIdsToDelete): bool {
    $this->db->transStart();

    foreach ($tiersToUpdate as $tier) {
      $this->db->table($this->table)
        ->where('id', $tier['id'])
        ->where('system_id', $systemId)
        ->set([
          'name' => $tier['name'],
          'min_level' => $tier['min_level'],
          'max_level' => $tier['max_level'],
          'active' => $tier['active'],
        ])
        ->update();
    }

    foreach ($tiersToInsert as $tier) {
      $this->db->table($this->table)->insert([
        'system_id' => $systemId,
        'name' => $tier['name'],
        'min_level' => $tier['min_level'],
        'max_level' => $tier['max_level'],
        'active' => $tier['active'],
      ]);
    }

    if (!empty($tierIdsToDelete)) {
      $this->db->table($this->table)
        ->where('system_id', $systemId)
        ->whereIn('id', $tierIdsToDelete)
        ->delete();
    }

    $this->db->transComplete();

    return $this->db->transStatus() === true;
  }

}
