<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger) {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
        helper('user');
    }

    protected function getUserUidFromJwt(): ?string {
        $authHeader = user_get_authorization_header($this->request);

        if (empty($authHeader) || !preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            return null;
        }

        $key = env('JWT_SECRET');

        if (empty($key)) {
            return null;
        }

        try {
            $decoded = JWT::decode($matches[1], new Key($key, 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($decoded->user)) {
            return null;
        }

        return (string) $decoded->user;
    }
}
