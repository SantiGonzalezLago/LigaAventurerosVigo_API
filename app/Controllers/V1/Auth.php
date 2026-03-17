<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class Auth extends BaseController {
  use ResponseTrait;

  public function login() {
    $response = [
      'message' => 'ok',
    ];

    return $this->respond($response, 200);
  }
}