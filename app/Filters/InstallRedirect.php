<?php

namespace App\Filters;

use App\Libraries\Install\InstallState;
use App\Libraries\Install\InstallUrl;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends every web request to the install page while the site is waiting to be installed, so a host that has just unpacked
 * the package shows the install page at its own address. It checks two files and the database name in the settings, and
 * never touches the database, so it costs nothing on an installed site.
 */
class InstallRedirect implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! InstallState::current()->open()) {
            return null;
        }

        // No settings exist yet, so links and redirects are built for the address this request came to.
        $app            = config('App');
        $url            = InstallUrl::current();
        $app->baseURL   = $url['baseURL'];
        $app->indexPage = $url['indexPage'];

        $path = trim($request->getUri()->getPath(), '/');
        $path = preg_replace('#^index\.php/?#', '', $path);
        if ($path === 'install' || str_starts_with((string) $path, 'install/')) {
            return null;
        }

        return redirect()->to(site_url('install'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
