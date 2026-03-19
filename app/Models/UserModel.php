<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model {

	protected $table = 'users';
	protected $primaryKey = 'uid';

	protected $allowedFields = [
		'uid',
		'email',
		'name',
		'verified',
		'master',
		'admin',
		'password',
		'date_created',
		'banned',
		'delete_on',
	];

	/**
	 * Obtener un usuario por su UID.
	 */
	public function getUser(string $uid): ?object {
		$builder = $this->db->table($this->table);
    $builder->where('uid', $uid);
    return $builder->get()->getRow();
	}

}
