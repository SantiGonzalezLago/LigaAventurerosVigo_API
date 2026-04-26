<?php

namespace App\Controllers\V1;

use App\Controllers\BaseController;

class Doc extends BaseController {

  public function index() {
    return view('docs/index');
  }
}
