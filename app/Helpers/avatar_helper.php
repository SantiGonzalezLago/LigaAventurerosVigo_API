<?php

if (!function_exists('build_avatar_url')) {
  function build_avatar_url(?string $avatarPath): ?string {
    if (empty($avatarPath)) {
      return null;
    }

    if (filter_var($avatarPath, FILTER_VALIDATE_URL) !== false) {
      return $avatarPath;
    }

    $normalizedPath = '/' . ltrim($avatarPath, '/');

    if (!str_starts_with($normalizedPath, '/images/avatar/')) {
      $normalizedPath = '/images/avatar/' . ltrim($avatarPath, '/');
    }

    return rtrim(base_url(), '/') . $normalizedPath;
  }
}
