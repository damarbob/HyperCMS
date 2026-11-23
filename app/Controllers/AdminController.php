<?php

namespace App\Controllers;

use App\Libraries\AdminShell;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AdminController
 *
 * AdminController provides a convenient place for loading components
 * and performing functions that are needed by all your admin controllers.
 * Extend this class in any new controllers:
 *     class Home extends AdminController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class AdminController extends BaseController
{

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        $this->data = (new AdminShell())->data($request);
    }
}
