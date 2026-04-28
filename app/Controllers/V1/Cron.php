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

	public function deleteUnusedAvatars() {
		$avatarDirectory = FCPATH . 'images/avatar/';

		if (!is_dir($avatarDirectory)) {
			return $this->respond([
				'message' => 'ok',
				'deleted_avatars' => 0,
			], 200);
		}

		$usersWithAvatar = $this->userModel
			->select('avatar')
			->where('avatar IS NOT NULL', null, false)
			->where("TRIM(avatar) <> ''", null, false)
			->findAll();

		$usedFilenames = [];

		foreach ($usersWithAvatar as $row) {
			$avatar = (string) ($row['avatar'] ?? '');

			if ($avatar === '') {
				continue;
			}

			$path = parse_url($avatar, PHP_URL_PATH);
			$filename = basename((string) ($path ?? $avatar));

			if ($filename !== '' && $filename !== '.' && $filename !== '..') {
				$usedFilenames[$filename] = true;
			}
		}

		$entries = scandir($avatarDirectory);

		if ($entries === false) {
			return $this->respond([
				'message' => 'No se pudo leer el directorio de avatares',
			], 500);
		}

		$deletedCount = 0;

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..' || $entry === '.gitignore') {
				continue;
			}

			$fullPath = $avatarDirectory . $entry;

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

		return $this->respond([
			'message' => 'ok',
			'deleted_avatars' => $deletedCount,
		], 200);
	}
}