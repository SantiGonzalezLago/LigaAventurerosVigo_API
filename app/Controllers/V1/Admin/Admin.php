<?php

namespace App\Controllers\V1\Admin;

use App\Controllers\V1\BaseApiController;
use App\Models\UploadLogModel;
use App\Models\UserModel;

class Admin extends BaseApiController {
  protected UserModel $userModel;
  protected UploadLogModel $uploadLogModel;

  public function __construct() {
    $this->userModel = new UserModel();
    $this->uploadLogModel = new UploadLogModel();
  }

  /**
   * Endpoint: GET /v1/admin/control-panel
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   *
   * Devuelve:
   * - 200: {
   *   message: "ok",
   *   users: {
   *     confirmed: number,
   *     unconfirmed: number,
   *     banned: number
   *   },
   *   files: {
   *     total: number,
   *     new: number
   *   }
   * }
   * - 401: { message: "No autorizado" }
   */
  public function controlPanel() {
    $stats = $this->userModel->getControlPanelUserStats();
    $uploadStats = $this->uploadLogModel->getControlPanelUploadStats();

    return $this->respond([
      'message' => 'ok',
      'users' => [
        'confirmed' => (int) ($stats->confirmed ?? 0),
        'unconfirmed' => (int) ($stats->unconfirmed ?? 0),
        'banned' => (int) ($stats->banned ?? 0),
      ],
      'files' => [
        'total' => (int) ($uploadStats->total ?? 0),
        'new' => (int) ($uploadStats->new_last_week ?? 0),
      ],
    ], 200);
  }

  /**
   * Endpoint: POST /v1/admin/upload-log
   *
   * Recibe:
   * - Authorization: Bearer <jwt>
   * - page (int, optional, default: 1)
   * - per_page (int, optional, default: 20)
   * - order_by (string, optional, default: timestamp)
   * - order_dir (string, optional, default: desc)
   *   - allowed values for order_by: id, user_uid, user_name, directory, file_name, remote_addr, size_bytes, timestamp
   *   - allowed values for order_dir: asc, desc
   * - q (string, optional, default: "")
   * - user_uid (string, optional, default: "")
   * - date_from (string, optional, formato: YYYY-MM-DD)
   * - date_to (string, optional, formato: YYYY-MM-DD)
   *
   * Devuelve:
   * - 200: {
   *   message: "ok",
   *   pagination: {
   *     page: number,
   *     per_page: number,
   *     total: number,
   *     total_pages: number,
   *     order_by: string,
   *     order_dir: string,
   *     q: string,
   *     user_uid: string,
   *     date_from: string|null,
   *     date_to: string|null
   *   },
   *   logs: [
   *     {
   *       id: number,
   *       user_uid: string,
   *       user_name: string|null,
   *       directory: string,
   *       file_name: string,
   *       remote_addr: string,
   *       size_bytes: number,
   *       note: string|null,
   *       exists: boolean,
   *       url: string|null,
   *       timestamp: string
   *     }
   *   ]
   * }
   * - 400: { message: "..." }
   * - 401: { message: "No autorizado" }
   */
  public function uploadLog() {
    $pageParam = $this->request->getVar('page');
    $perPageParam = $this->request->getVar('per_page');
    $qParam = $this->request->getVar('q');
    $userUidParam = $this->request->getVar('user_uid');
    $dateFromParam = $this->request->getVar('date_from');
    $dateToParam = $this->request->getVar('date_to');

    $page = (is_numeric($pageParam) && $pageParam !== '') ? (int) $pageParam : 1;
    $perPage = (is_numeric($perPageParam) && $perPageParam !== '') ? (int) $perPageParam : 20;
    $orderBy = (string) ($this->request->getVar('order_by') ?? 'timestamp');
    $orderDir = (string) ($this->request->getVar('order_dir') ?? 'desc');
    $q = is_string($qParam) ? trim($qParam) : '';
    $userUid = is_string($userUidParam) ? trim($userUidParam) : '';
    $perPage = min(255, max(1, $perPage));

    $dateFromInput = is_string($dateFromParam) ? trim($dateFromParam) : '';
    $dateToInput = is_string($dateToParam) ? trim($dateToParam) : '';
    $dateFrom = $this->normalizeDateFilter($dateFromInput);
    $dateTo = $this->normalizeDateFilter($dateToInput);

    if ($dateFromInput !== '' && $dateFrom === null) {
      return $this->respond(['message' => 'El campo date_from no tiene un formato valido'], 400);
    }

    if ($dateToInput !== '' && $dateTo === null) {
      return $this->respond(['message' => 'El campo date_to no tiene un formato valido'], 400);
    }

    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
      return $this->respond(['message' => 'El campo date_from no puede ser posterior a date_to'], 400);
    }

    $dateFromFilter = $dateFrom !== null ? $dateFrom . ' 00:00:00' : null;
    $dateToFilter = $dateTo !== null ? $dateTo . ' 23:59:59' : null;

    $allowedOrderBy = [
      'id' => 'file_upload_log.id',
      'user_uid' => 'file_upload_log.user_uid',
      'user_name' => 'users.name',
      'directory' => 'file_upload_log.directory',
      'file_name' => 'file_upload_log.file_name',
      'remote_addr' => 'file_upload_log.remote_addr',
      'size_bytes' => 'file_upload_log.size_bytes',
      'timestamp' => 'file_upload_log.timestamp',
    ];

    $resolvedOrderBy = $allowedOrderBy[$orderBy] ?? $allowedOrderBy['timestamp'];
    $resolvedOrderByField = array_search($resolvedOrderBy, $allowedOrderBy, true) ?: 'timestamp';
    $resolvedOrderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

    $total = $this->uploadLogModel->getTotalUploadLogs($q, $userUid, $dateFromFilter, $dateToFilter);
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

    $page = max(1, min($page, $totalPages));

    $offset = ($page - 1) * $perPage;
    $items = $this->uploadLogModel->getUploadLogs($offset, $perPage, $resolvedOrderBy, $resolvedOrderDir, $q, $userUid, $dateFromFilter, $dateToFilter);
    $items = array_map(function ($item) {
      $directory = (string) ($item->directory ?? '');
      $fileName = (string) ($item->file_name ?? '');
      $item->exists = is_file($this->buildUploadFilePath($directory, $fileName));

      if ($item->exists) {
        $normalizedDirectory = trim($directory, " \t\n\r\0\x0B/");
        $item->url = base_url($normalizedDirectory . '/' . $fileName);
      }

      return $item;
    }, $items);

    return $this->respond([
      'message' => 'ok',
      'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'order_by' => $resolvedOrderByField,
        'order_dir' => $resolvedOrderDir,
        'q' => $q,
        'user_uid' => $userUid,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
      ],
      'logs' => $items,
    ], 200);
  }

  private function normalizeDateFilter(string $rawValue): ?string {
    if ($rawValue === '') {
      return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawValue) !== 1) {
      return null;
    }

    try {
      $date = new \DateTimeImmutable($rawValue);
    } catch (\Throwable $e) {
      return null;
    }

    return $date->format('Y-m-d');
  }

  private function buildUploadFilePath(string $directory, string $fileName): string {
    $normalizedDirectory = trim($directory, " \t\n\r\0\x0B/");
    $normalizedFileName = trim($fileName);

    return rtrim(FCPATH, DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR
      . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalizedDirectory)
      . DIRECTORY_SEPARATOR
      . $normalizedFileName;
  }
}