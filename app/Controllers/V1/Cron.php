<?php

namespace App\Controllers\V1;

use App\Models\UserModel;

class Cron extends BaseApiController {

	private UserModel $userModel;

	public function __construct() {
		$this->userModel = new UserModel();
	}

	public function deleteUsersByRequest() {
		$today = (new \DateTimeImmutable('today'))->format('Y-m-d');

		$usersToDelete = $this->userModel
			->select('uid')
			->where('delete_on <=', $today)
			->findAll();

		if (empty($usersToDelete)) {
			return $this->respond([
				'message' => 'ok',
				'deleted_users' => 0,
			], 200);
		}

		$uids = array_values(array_unique(array_filter(array_map(static function (array $row): string {
			return (string) ($row['uid'] ?? '');
		}, $usersToDelete))));

		if (empty($uids)) {
			return $this->respond([
				'message' => 'ok',
				'deleted_users' => 0,
			], 200);
		}

		$deleted = (bool) $this->userModel
			->builder()
			->whereIn('uid', $uids)
			->delete();

		if (!$deleted) {
			return $this->respond([
				'message' => 'No se han podido eliminar los usuarios solicitados',
			], 500);
		}

		return $this->respond([
			'message' => 'ok',
			'deleted_users' => count($uids),
		], 200);
	}

	public function deleteUnusedFiles() {
		$deletedAvatars = $this->cleanUnusedFilesInDirectory(
			FCPATH . 'images/avatar/',
			$this->getUsedAvatarFilenames()
		);

		$deletedIcons = $this->cleanUnusedFilesInDirectory(
			FCPATH . 'images/system/',
			$this->getUsedSystemIconFilenames()
		);

		return $this->respond([
			'message' => 'ok',
			'deleted_avatars' => $deletedAvatars ?? 0,
			'deleted_icons' => $deletedIcons ?? 0,
		], 200);
	}

	private function getUsedAvatarFilenames(): array {
		$rows = $this->userModel
			->select('avatar')
			->where('avatar IS NOT NULL', null, false)
			->where("TRIM(avatar) <> ''", null, false)
			->findAll();

		$used = [];

		foreach ($rows as $row) {
			$avatar = (string) ($row['avatar'] ?? '');

			if ($avatar === '') {
				continue;
			}

			$path = parse_url($avatar, PHP_URL_PATH);
			$filename = basename((string) ($path ?? $avatar));

			if ($filename !== '' && $filename !== '.' && $filename !== '..') {
				$used[$filename] = true;
			}
		}

		return $used;
	}

	private function getUsedSystemIconFilenames(): array {
		$db = \Config\Database::connect();
		$rows = $db->table('system')
			->select('icon')
			->where('icon IS NOT NULL', null, false)
			->where("TRIM(icon) <> ''", null, false)
			->get()
			->getResult();

		$used = [];

		foreach ($rows as $row) {
			$icon = (string) ($row->icon ?? '');

			if ($icon === '') {
				continue;
			}

			$path = parse_url($icon, PHP_URL_PATH);
			$filename = basename((string) ($path ?? $icon));

			if ($filename !== '' && $filename !== '.' && $filename !== '..') {
				$used[$filename] = true;
			}
		}

		return $used;
	}

	/**
	 * Elimina los ficheros de un directorio que no estén en $usedFilenames.
	 * Devuelve el número de ficheros eliminados, o null si no se pudo leer el directorio.
	 */
	private function cleanUnusedFilesInDirectory(string $directory, array $usedFilenames): ?int {
		if (!is_dir($directory)) {
			return 0;
		}

		$entries = scandir($directory);

		if ($entries === false) {
			return null;
		}

		$deletedCount = 0;

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..' || $entry === '.gitignore') {
				continue;
			}

			$fullPath = $directory . $entry;

			if (!is_file($fullPath)) {
				continue;
			}

			if (isset($usedFilenames[$entry])) {
				continue;
			}

			if (@unlink($fullPath)) {
				$deletedCount++;
			}
		}

		return $deletedCount;
	}
}