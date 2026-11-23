<?php

namespace App\Modules;

use App\Controllers\BaseController;
use App\Libraries\AdminShell;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * The base of every controller of a module. It gives a page the layout data and the menu of the admin, and a few helpers
 * that cover what a module needs, so a module never extends a controller of core.
 */
abstract class ModuleController extends BaseController
{
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        $this->data = (new AdminShell())->data($request);
    }

    /**
     * Draw a view of the module inside the admin layout.
     *
     * @param array<string, mixed> $data
     */
    protected function page(string $view, array $data = []): string
    {
        return view($view, array_merge($this->data, $data));
    }

    /**
     * Whether the signed-in person has an ability, or an action on a model. An ability nobody registered reads as no.
     */
    protected function allowed(string $ability, ?int $modelId = null): bool
    {
        try {
            return can($ability, $modelId);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Answer with JSON.
     *
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($payload);
    }

    /**
     * Go to an address with a message that the next page shows.
     */
    protected function leave(string $to, string $message, string $type = 'error')
    {
        session()->setFlashdata($type === 'ok' ? 'success' : $type, $message);

        return redirect()->to($to);
    }
}
