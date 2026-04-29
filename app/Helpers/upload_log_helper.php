<?php

if (!function_exists('upload_log_create')) {
	function upload_log_create(
		string $userUid,
		string $directory,
		string $fileName,
		?string $note = null
	): bool {
		$directory = trim($directory, " \t\n\r\0\x0B/");
		$fileName = trim($fileName);
		$targetPath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory) . DIRECTORY_SEPARATOR . $fileName;
		$sizeBytes = @filesize($targetPath);

		if ($fileName === '' || $sizeBytes === false) {
			return false;
		}

		$db = \Config\Database::connect();
		$request = service('request');

		return (bool) $db->table('file_upload_log')->insert([
			'user_uid' => $userUid,
			'directory' => $directory,
			'file_name' => $fileName,
			'remote_addr' => trim((string) $request->getIPAddress()),
			'size_bytes' => (int) $sizeBytes,
			'note' => $note,
		]);
	}
}