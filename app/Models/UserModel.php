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
    'avatar',
    'verified',
    'master',
    'admin',
    'password',
    'date_created',
    'banned',
    'delete_on',
  ];

  public function getUser(string $uid): ?object {
    $builder = $this->db->table($this->table);
    $builder->where('uid', $uid);
    return $builder->get()->getRow();
  }

  public function getUserByEmail(string $email): ?object {
    $builder = $this->db->table($this->table);
    $builder->where('email', $email);
    return $builder->get()->getRow();
  }

  public function getUserByProvider(string $provider, string $providerId): ?object {
    $builder = $this->db->table($this->table);
    $builder->select('users.*');
    $builder->join('user_provider', 'users.uid = user_provider.user_uid');
    $builder->where('user_provider.provider', $provider);
    $builder->where('user_provider.provider_id', $providerId);
    return $builder->get()->getRow();
  }

  public function getLegacyUserByEmail(string $email): ?object {
    $builder = $this->db->table($this->table);
    $builder->select('users.*');
    $builder->where('email', $email);
    $builder->where('NOT EXISTS (SELECT 1 FROM user_provider WHERE user_provider.user_uid = users.uid)', null, false);
    return $builder->get()->getRow();
  }

  public function insertUserProvider(string $userUid, string $provider, string $providerId): bool {
    try {
      $builder = $this->db->table('user_provider');
      $builder->insert([
        'user_uid' => $userUid,
        'provider' => $provider,
        'provider_id' => $providerId,
      ]);
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

}
