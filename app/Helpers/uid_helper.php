<?php

if (!function_exists('generate_uid')) {
  function generate_uid(): string {
    $bytes = random_bytes(8);
    $base64 = base64_encode($bytes);
    return rtrim(strtr($base64, '+/', '-_'), '=');
  }
}