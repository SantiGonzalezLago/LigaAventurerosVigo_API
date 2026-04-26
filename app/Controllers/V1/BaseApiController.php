<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

abstract class BaseApiController extends BaseController {
  use ResponseTrait;
}