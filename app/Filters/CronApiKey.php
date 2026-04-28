<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class CronApiKey implements FilterInterface {

	public function before(RequestInterface $request, $arguments = null) {
		$configuredKey = (string) env('CRON_API_KEY', '');
		$providedKey = (string) ($request->getGet('key') ?? '');

		if ($configuredKey === '') {
			return Services::response()
				->setStatusCode(500)
				->setJSON(['message' => 'Cron API key is not configured']);
		}

		if (!hash_equals($configuredKey, $providedKey)) {
			return Services::response()
				->setStatusCode(401)
				->setJSON(['message' => 'Unauthorized']);
		}

		return null;
	}

	public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {
	}
}