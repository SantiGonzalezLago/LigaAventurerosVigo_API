<?php

namespace App\Controllers\V1;

use App\Models\UserModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class User extends BaseApiController {
	protected UserModel $userModel;

	public function __construct() {
		$this->userModel = new UserModel();
	}

	/**
	 * Endpoint: GET /v1/me
	 *
	 * Recibe:
	 * - Authorization: Bearer <jwt>
	 *
	 * Devuelve:
	 * - 200: { message: "ok", user: { uid, jwt, name, email, avatar, verified, master, admin } }
	 * - 401: { message: "No autorizado" }
	 */
	public function me() {
		$user = $this->getUserFromJwt();

		if ($user === null) {
			return $this->respond([
				'message' => 'No autorizado',
			], 401);
		}

		return $this->respond([
			'message' => 'ok',
			'user' => $this->generateUserdata($user),
		], 200);
	}

	/**
	 * Endpoint: POST /v1/update-settings
	 *
	 * Recibe (todos opcionales, pero al menos uno es obligatorio):
	 * - name (string)
	 * - password (string, texto plano)
	 * - avatar (archivo de imagen)
	 *
	 * Devuelve:
	 * - 200: { message: "ok", user: { uid, name, email, avatar, verified, master, admin } }
	 * - 400: { message: "..." }
	 * - 401: { message: "No autorizado" }
	 */
	public function updateSettings() {
		$user = $this->getUserFromJwt();

		if ($user === null) {
			return $this->respond([
				'message' => 'No autorizado',
			], 401);
		}

		$nameParam = $this->request->getVar('name');
		$passwordParam = $this->request->getVar('password');
		$avatarFile = $this->request->getFile('avatar');

		$name = is_string($nameParam) ? trim($nameParam) : '';
		$password = is_string($passwordParam) ? trim($passwordParam) : '';
		$hasAvatar = $avatarFile !== null && $avatarFile->getError() !== UPLOAD_ERR_NO_FILE;

		if (!$name && !$password && !$hasAvatar) {
			return $this->respond([
				'message' => 'Debes enviar al menos uno de estos campos: name, password o avatar',
			], 400);
		}

		$updateData = [];

		if ($name) {
			$updateData['name'] = $name;
		}

		if ($password) {
			$updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
		}

		if ($hasAvatar) {
			if (!$avatarFile->isValid()) {
				return $this->respond([
					'message' => 'El avatar enviado no es válido',
				], 400);
			}

			$avatarFilename = $this->storeAvatar($user->uid, $avatarFile);

			if ($avatarFilename === null) {
				return $this->respond([
					'message' => 'No se pudo procesar el avatar',
				], 400);
			}

			$updateData['avatar'] = $avatarFilename;
		}

		if (empty($updateData)) {
			return $this->respond([
				'message' => 'No hay cambios válidos para actualizar',
			], 400);
		}

		$updated = $this->userModel->update($user->uid, $updateData);

		if (!$updated) {
			return $this->respond([
				'message' => 'No se pudieron actualizar los datos del usuario',
			], 500);
		}

    $updatedUser = $this->userModel->getUser($user->uid);

		return $this->respond([
			'message' => 'Configuración actualizada correctamente',
			'user' => $this->generateUserdata($updatedUser),
		], 200);
	}

	/**
	 * Endpoint: DELETE /v1/delete-user
	 *
	 * Recibe:
	 * - Authorization: Bearer <jwt>
	 *
	 * Devuelve:
	 * - 200: { message: "ok", delete_on: "YYYY-MM-DD" }
	 * - 401: { message: "No autorizado" }
	 * - 500: { message: "No se pudo programar la eliminación de la cuenta" }
	 */
	public function deleteUser() {
		$user = $this->getUserFromJwt();

		if ($user === null) {
			return $this->respond([
				'message' => 'No autorizado',
			], 401);
		}

		$deleteOn = $this->userModel->scheduleDeleteOn($user->uid, 15);

		if ($deleteOn === null) {
			return $this->respond([
				'message' => 'No se pudo programar la eliminación de la cuenta',
			], 500);
		}

		return $this->respond([
			'message' => 'ok',
			'delete_on' => $deleteOn,
		], 200);
	}

	private function storeAvatar(string $uid, UploadedFile $avatarFile): ?string {
		$allowedMimeTypes = [
			'image/jpeg' => 'jpg',
			'image/jpg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
		];

		$mimeType = strtolower((string) $avatarFile->getMimeType());

		if (!isset($allowedMimeTypes[$mimeType])) {
			return null;
		}

		$avatarDirectory = FCPATH . 'images/avatar/';

		if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
			return null;
		}

		$extension = $allowedMimeTypes[$mimeType];
		$filename = $uid . '_' . date('YmdHis') . '.' . $extension;
		$targetPath = $avatarDirectory . $filename;

		try {
			$avatarFile->move($avatarDirectory, $filename, true);
		} catch (\Throwable $e) {
			return null;
		}

		$imageInfo = @getimagesize($targetPath);

		if ($imageInfo === false) {
			@unlink($targetPath);
			return null;
		}

		$width = (int) ($imageInfo[0] ?? 0);
		$height = (int) ($imageInfo[1] ?? 0);

		if ($width > 256 || $height > 256) {
			$masterDimension = $width >= $height ? 'width' : 'height';

			try {
				service('image')
					->withFile($targetPath)
					->resize(256, 256, true, $masterDimension)
					->save($targetPath);
			} catch (\Throwable $e) {
				@unlink($targetPath);
				return null;
			}
		}

		return $filename;
	}

}