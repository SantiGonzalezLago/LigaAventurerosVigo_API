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

  public function getControlPanelUserStats(): ?object {
    $now = date('Y-m-d H:i:s');

    $sql = "
      SELECT
        SUM(CASE WHEN active_bans.user_uid IS NULL AND users.verified = 1 THEN 1 ELSE 0 END) AS confirmed,
        SUM(CASE WHEN active_bans.user_uid IS NULL AND (users.verified <> 1 OR users.verified IS NULL) THEN 1 ELSE 0 END) AS unconfirmed,
        SUM(CASE WHEN active_bans.user_uid IS NOT NULL THEN 1 ELSE 0 END) AS banned
      FROM users
      LEFT JOIN (
        SELECT DISTINCT user_uid
        FROM user_ban
        WHERE date_start <= ?
          AND (permanent = 1 OR date_end > ?)
      ) AS active_bans ON active_bans.user_uid = users.uid
    ";

    return $this->db->query($sql, [$now, $now])->getFirstRow();
  }

  public function getTotalUsers(string $q = NULL): int {
    $now = date('Y-m-d H:i:s');
    $escapedNow = $this->db->escape($now);
    $activeBansSubquery = "(
      SELECT DISTINCT user_uid
      FROM user_ban
      WHERE date_start <= {$escapedNow}
        AND (permanent = 1 OR date_end > {$escapedNow})
    ) active_bans";

    $countBuilder = $this->db->table($this->table . ' users');
    $countBuilder->join($activeBansSubquery, 'active_bans.user_uid = users.uid', 'left', false);

    if ($q) {
      $countBuilder->groupStart();
      $countBuilder->like('users.uid', $q);
      $countBuilder->orLike('users.name', $q);
      $countBuilder->orLike('users.email', $q);
      $countBuilder->groupEnd();
    }

    return (int) ($countBuilder
      ->select('COUNT(DISTINCT users.uid) AS total', false)
      ->get()
      ->getRow('total') ?? 0);
  }

  public function getUsers(int $offset, int $limit, string $orderBy, string $orderDir, string $q = NULL): array {
    $now = date('Y-m-d H:i:s');
    $escapedNow = $this->db->escape($now);
    $activeBansSubquery = "(
      SELECT DISTINCT user_uid
      FROM user_ban
      WHERE date_start <= {$escapedNow}
        AND (permanent = 1 OR date_end > {$escapedNow})
    ) active_bans";

    $dataBuilder = $this->db->table($this->table . ' users');
    $dataBuilder->join($activeBansSubquery, 'active_bans.user_uid = users.uid', 'left', false);
    $dataBuilder->select([
      'users.uid',
      'users.name',
      'users.email',
      'users.avatar',
      'users.verified',
      'users.admin',
      'users.master',
      'users.date_created',
    ]);
    $dataBuilder->select('CASE WHEN active_bans.user_uid IS NULL THEN 0 ELSE 1 END AS banned', false);

    if ($q) {
      $dataBuilder->groupStart();
      $dataBuilder->like('users.uid', $q);
      $dataBuilder->orLike('users.name', $q);
      $dataBuilder->orLike('users.email', $q);
      $dataBuilder->groupEnd();
    }

    return $dataBuilder
      ->orderBy($orderBy, $orderDir)
      ->limit($limit, $offset)
      ->get()
      ->getResult();
  }

  public function getUserForAdmin(string $uid): ?object {
    $now = date('Y-m-d H:i:s');
    $escapedNow = $this->db->escape($now);
    $activeBansSubquery = "(
      SELECT DISTINCT user_uid
      FROM user_ban
      WHERE date_start <= {$escapedNow}
        AND (permanent = 1 OR date_end > {$escapedNow})
    ) active_bans";

    $builder = $this->db->table($this->table . ' users');
    $builder->join($activeBansSubquery, 'active_bans.user_uid = users.uid', 'left', false);
    $builder->select([
      'users.uid',
      'users.name',
      'users.email',
      'users.avatar',
      'users.verified',
      'users.admin',
      'users.master',
      'users.date_created',
    ]);
    $builder->select('CASE WHEN active_bans.user_uid IS NULL THEN 0 ELSE 1 END AS banned', false);
    $builder->where('users.uid', $uid);

    return $builder->get()->getRow();
  }

  public function getUserBanHistory(string $uid): array {
    $now = date('Y-m-d H:i:s');
    $escapedNow = $this->db->escape($now);

    $builder = $this->db->table('user_ban');
    $builder->select([
      'id',
      'reason',
      'permanent',
      'date_start',
      'date_end',
    ]);
    $builder->select("CASE
      WHEN date_start <= {$escapedNow} AND (permanent = 1 OR date_end > {$escapedNow}) THEN 1
      ELSE 0
    END AS active", false);
    $builder->where('user_uid', $uid);

    return $builder
      ->orderBy('date_start', 'desc')
      ->get()
      ->getResult();
  }

}
