<?php

namespace App\Filters;

use App\Libraries\Install\InstallState;
use App\Libraries\Upgrade\Steps\Steps;
use App\Libraries\Upgrade\UpgradeState;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends every web request to the upgrade page while the data of the site is behind the code, so code that was uploaded over an
 * older site shows the upgrade page and not errors about tables that are not there. Scripts that use the token API are told to
 * come back later. A marker file holds the version the site was last found at, so a site that is current costs one file read.
 */
class UpgradeRedirect implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $state = UpgradeState::current();
        if ($state->known(Steps::LATEST)) {
            return null;
        }

        // The install page has its own turn, and an unconfigured site has no data to be behind.
        if (InstallState::current()->open()) {
            return null;
        }

        $path = trim($request->getUri()->getPath(), '/');
        $path = (string) preg_replace('#^index\.php/?#', '', $path);
        if ($path === 'upgrade' || str_starts_with($path, 'upgrade/')) {
            return null;
        }

        if ($state->needed(db_connect(), Steps::LATEST) !== true) {
            return null;
        }

        if ($path === 'api' || str_starts_with($path, 'api/')) {
            return service('response')
                ->setStatusCode(503)
                ->setHeader('Retry-After', '300')
                ->setContentType('text/plain', 'utf-8')
                ->setBody(lang('Upgrade.unavailable') . "\n");
        }

        return redirect()->to(site_url('upgrade'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
