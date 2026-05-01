<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model {

  protected $table = 'user';
  protected $primaryKey = 'uid';

  protected $allowedFields = [
    'uid',
    'email',
    'name',
    'avatar',
    'verified',
    'password',
    'date_created',
    'banned',
    'delete_on',
  ];

  public function getUser(string $uid): ?object {
    $builder = $this->db->table($this->table);
    $builder->where('uid', $uid);
    return $this->withRoles($builder->get()->getRow());
  }

  public function getUserByEmail(string $email): ?object {
    $builder = $this->db->table($this->table);
    $builder->where('email', $email);
    return $this->withRoles($builder->get()->getRow());
  }

  public function getUserByProvider(string $provider, string $providerId): ?object {
    $builder = $this->db->table($this->table);
    $builder->select('user.*');
    $builder->join('user_provider', 'user.uid = user_provider.user_uid');
    $builder->where('user_provider.provider', $provider);
    $builder->where('user_provider.provider_id', $providerId);
    return $this->withRoles($builder->get()->getRow());
  }

  public function getLegacyUserByEmail(string $email): ?object {
    $builder = $this->db->table($this->table);
    $builder->select('user.*');
    $builder->where('email', $email);
    $builder->where('NOT EXISTS (SELECT 1 FROM user_provider WHERE user_provider.user_uid = user.uid)', null, false);
    return $this->withRoles($builder->get()->getRow());
  }

  public function getUserRoles(string $uid): array {
    $rows = $this->db->table('user_role')
      ->select('role')
      ->where('user_uid', $uid)
      ->orderBy('role', 'asc')
      ->get()
      ->getResult();

    $roles = array_map(static function ($row) {
      return (string) ($row->role ?? '');
    }, $rows);

    return array_values(array_filter($roles, static function (string $role) {
      return $role !== '';
    }));
  }

  public function userHasRole(string $uid, string $role): bool {
    if (!$this->isAllowedRole($role)) {
      return false;
    }

    $row = $this->db->table('user_role')
      ->select('role')
      ->where('user_uid', $uid)
      ->where('role', $role)
      ->limit(1)
      ->get()
      ->getFirstRow();

    return $row !== null;
  }

  public function setUserRole(string $uid, string $role, bool $enabled): bool {
    if (!$this->isAllowedRole($role)) {
      return false;
    }

    $builder = $this->db->table('user_role');

    if ($enabled) {
      if ($this->userHasRole($uid, $role)) {
        return true;
      }

      return (bool) $builder->insert([
        'user_uid' => $uid,
        'role' => $role,
      ]);
    }

    $builder->where('user_uid', $uid);
    $builder->where('role', $role);
    $builder->delete();

    return $this->db->error()['code'] === 0;
  }

  public function clearDeleteOn(string $uid): bool {
    return (bool) $this->db->table($this->table)
      ->where('uid', $uid)
      ->set(['delete_on' => null])
      ->update();
  }

  public function scheduleDeleteOn(string $uid, int $days = 15): ?string {
    $deleteOn = (new \DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');

    $updated = (bool) $this->db->table($this->table)
      ->where('uid', $uid)
      ->set(['delete_on' => $deleteOn])
      ->update();

    return $updated ? $deleteOn : null;
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
      FROM user users
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
    $dataBuilder->join('(SELECT user_uid, GROUP_CONCAT(role ORDER BY role SEPARATOR ",") AS roles FROM user_role GROUP BY user_uid) AS role_agg', 'role_agg.user_uid = users.uid', 'left', false);
    $dataBuilder->select([
      'users.uid',
      'users.name',
      'users.email',
      'users.avatar',
      'users.verified',
      'users.date_created',
      'COALESCE(role_agg.roles, "") AS roles',
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
    $builder->join('(SELECT user_uid, GROUP_CONCAT(role ORDER BY role SEPARATOR ",") AS roles FROM user_role GROUP BY user_uid) AS role_agg', 'role_agg.user_uid = users.uid', 'left', false);
    $builder->select([
      'users.uid',
      'users.name',
      'users.email',
      'users.avatar',
      'users.verified',
      'users.date_created',
      'COALESCE(role_agg.roles, "") AS roles',
    ]);
    $builder->select('CASE WHEN active_bans.user_uid IS NULL THEN 0 ELSE 1 END AS banned', false);
    $builder->where('users.uid', $uid);

    return $builder->get()->getRow();
  }

  private function withRoles(?object $user): ?object {
    if ($user === null) {
      return null;
    }

    $user->roles = $this->getUserRoles((string) $user->uid);
    return $user;
  }

  private function isAllowedRole(string $role): bool {
    return in_array($role, ['admin', 'master', 'vip'], true);
  }

  public function getUserBanHistory(string $uid): array {
    $now = date('Y-m-d H:i:s');
    $escapedNow = $this->db->escape($now);

    $builder = $this->db->table('user_ban');
    $builder->select([
      'user_ban.id',
      'user_ban.reason',
      'user_ban.permanent',
      'user_ban.date_start',
      'user_ban.date_end',
      'user_ban.banned_by',
      'admin_user.name AS banned_by_name',
    ]);
    $builder->select("CASE
      WHEN user_ban.date_start <= {$escapedNow} AND (user_ban.permanent = 1 OR user_ban.date_end > {$escapedNow}) THEN 1
      ELSE 0
    END AS active", false);
    $builder->join('user admin_user', 'admin_user.uid = user_ban.banned_by', 'left');
    $builder->where('user_ban.user_uid', $uid);

    return $builder
      ->orderBy('user_ban.date_start', 'desc')
      ->get()
      ->getResult();
  }

  public function liftActiveBans(string $userUid): int {
    $now = date('Y-m-d H:i:s');

    $this->db->table('user_ban')
      ->where('user_uid', $userUid)
      ->where('date_start <=', $now)
      ->groupStart()
        ->where('permanent', 1)
        ->orWhere('date_end >', $now)
      ->groupEnd()
      ->set([
        'permanent' => 0,
        'date_end'  => $now,
      ])
      ->update();

    return $this->db->affectedRows();
  }

  public function createUserBan(string $userUid, string $bannedBy, bool $permanent, ?string $dateEnd, string $reason, ?string $dateStart = null): bool {
    $builder = $this->db->table('user_ban');

    return (bool) $builder->insert([
      'user_uid' => $userUid,
      'banned_by' => $bannedBy,
      'reason' => $reason,
      'permanent' => $permanent ? 1 : 0,
      'date_start' => $dateStart ?? date('Y-m-d H:i:s'),
      'date_end' => $permanent ? null : $dateEnd,
    ]);
  }

}
