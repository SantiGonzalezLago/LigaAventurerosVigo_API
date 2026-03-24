<?php

if (!function_exists('user_get_authorization_header')) {
  function user_get_authorization_header(\CodeIgniter\HTTP\RequestInterface $request): ?string {
    $authHeader = trim($request->getHeaderLine('Authorization'));

    if ($authHeader !== '') {
      return $authHeader;
    }

    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $serverKey) {
      $value = $request->getServer($serverKey);

      if (is_string($value) && trim($value) !== '') {
        return trim($value);
      }
    }

    if (function_exists('apache_request_headers')) {
      $headers = apache_request_headers();

      if (is_array($headers)) {
        foreach ($headers as $name => $value) {
          if (strcasecmp((string) $name, 'Authorization') === 0 && is_string($value) && trim($value) !== '') {
            return trim($value);
          }
        }
      }
    }

    return null;
  }
}

if (!function_exists('user_is_banned')) {
  function user_is_banned(string $uid): bool {
    $db = \Config\Database::connect();
    $now = date('Y-m-d H:i:s');

    $activeBan = $db->table('user_ban')
      ->select('id')
      ->where('user_uid', $uid)
      ->where('date_start <=', $now)
      ->groupStart()
        ->where('permanent', 1)
        ->orWhere('date_end >', $now)
      ->groupEnd()
      ->limit(1)
      ->get()
      ->getFirstRow();

    return $activeBan !== null;
  }
}
