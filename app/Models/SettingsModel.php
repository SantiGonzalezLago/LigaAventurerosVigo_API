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

}
