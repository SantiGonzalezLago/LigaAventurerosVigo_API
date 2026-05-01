<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class UploadLogModel extends Model {

  protected $table = 'file_upload_log';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'user_uid',
    'directory',
    'file_name',
    'remote_addr',
    'size_bytes',
    'note',
    'timestamp',
  ];

  public function getControlPanelUploadStats(): ?object {
    $weekAgo = (new \DateTimeImmutable('-7 days'))->format('Y-m-d H:i:s');

    $sql = "
      SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN timestamp >= ? THEN 1 ELSE 0 END) AS new_last_week
      FROM file_upload_log
    ";

    return $this->db->query($sql, [$weekAgo])->getFirstRow();
  }

  public function getTotalUploadLogs(string $q = '', string $userUid = '', ?string $dateFrom = null, ?string $dateTo = null): int {
    $builder = $this->db->table($this->table);
    $builder->join('user users', 'users.uid = file_upload_log.user_uid', 'left');

    $this->applyFilters($builder, $q, $userUid, $dateFrom, $dateTo);

    return (int) ($builder
      ->select('COUNT(*) AS total', false)
      ->get()
      ->getRow('total') ?? 0);
  }

  public function getUploadLogs(int $offset, int $limit, string $orderBy, string $orderDir, string $q = '', string $userUid = '', ?string $dateFrom = null, ?string $dateTo = null): array {
    $builder = $this->db->table($this->table);
    $builder->join('user users', 'users.uid = file_upload_log.user_uid', 'left');
    $builder->select([
      'file_upload_log.id',
      'file_upload_log.user_uid',
      'users.name AS user_name',
      'file_upload_log.directory',
      'file_upload_log.file_name',
      'file_upload_log.remote_addr',
      'file_upload_log.size_bytes',
      'file_upload_log.note',
      'file_upload_log.timestamp',
    ]);

    $this->applyFilters($builder, $q, $userUid, $dateFrom, $dateTo);

    return $builder
      ->orderBy($orderBy, $orderDir)
      ->limit($limit, $offset)
      ->get()
      ->getResult();
  }

  private function applyFilters(BaseBuilder $builder, string $q = '', string $userUid = '', ?string $dateFrom = null, ?string $dateTo = null): void {
    if ($userUid !== '') {
      $builder->where('file_upload_log.user_uid', $userUid);
    }

    if ($dateFrom !== null) {
      $builder->where('file_upload_log.timestamp >=', $dateFrom);
    }

    if ($dateTo !== null) {
      $builder->where('file_upload_log.timestamp <=', $dateTo);
    }

    if ($q !== '') {
      $builder->groupStart();
      $builder->like('users.uid', $q);
      $builder->orLike('users.name', $q);
      $builder->orLike('file_upload_log.note', $q);
      $builder->groupEnd();
    }
  }

}