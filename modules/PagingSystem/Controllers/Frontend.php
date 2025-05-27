<?php

namespace PagingSystem\Controllers;

use App\Controllers\BaseController;
use App\Services\HyperHooks;

class Frontend extends BaseController
{
    public function index()
    {
        $path = service('request')->getPath() ?: 'home';
        $page = HyperHooks::getInstance()->trigger(hook('PagingSystemBackend.controller:frontend:index'), [
            'path' => $path,
        ], false);

        $page = HyperHooks::getInstance()->filter(hook('PagingSystemBackend.controller:frontend:index:pagehtml'), $page);

        return !empty($page) ? $page : view('app');
    }
}
