<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model {

	protected $table = 'settings';
	protected $primaryKey = 'key';

	protected $allowedFields = [
		'key',
		'description',
		'value',
	];

	public function getSetting(string $key): ?string {
		$builder = $this->db->table($this->table);
		$builder->where('key', $key);
    $row = $builder->get()->getRow();
    return $row ? $row->value : null;
	}

  public function getAllSettings(): array {
    return $this->db->table($this->table)
      ->orderBy('key', 'asc')
      ->get()
      ->getResult();
  }

  public function updateSetting(string $key, string $value): bool {
    $exists = $this->db->table($this->table)
      ->where('key', $key)
      ->countAllResults() > 0;

    if (!$exists) {
      return false;
    }

    return (bool) $this->db->table($this->table)
      ->where('key', $key)
      ->set(['value' => $value])
      ->update();
  }

}