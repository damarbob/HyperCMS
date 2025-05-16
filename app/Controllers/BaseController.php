<?php

namespace App\Controllers;

use App\Libraries\SyntaxProcessor;
use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use App\Models\ModelDataModel;
use App\Models\ModelsModel;
use App\Services\EntriesManager;
use App\Services\HyperHooks;
use App\Services\ModelsManager;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Hyper;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;
    protected HyperHooks $hooks;
    protected ModelsManager $modelsManager;
    protected EntriesManager $entriesManager;

    // Models
    protected ModelsModel $modelsModel;
    protected ModelDataModel $modelDataModel;
    protected EntriesModel $entriesModel;
    protected EntryDataModel $entryDataModel;

    // Data to be passed to views
    protected array $data;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = service('session');

        $this->hooks = service('hooks');
        $this->modelsManager = service('modelsManager');
        $this->entriesManager = service('entriesManager');

        // Models
        $this->modelsModel = model('modelsModel');
        $this->modelDataModel = model('modelDataModel');
        $this->entriesModel = model('entriesModel');
        $this->entryDataModel = model('entryDataModel');

        // Preinit data
        $this->data['hyper']['config'] = [
            "baseUrl" => base_url(),
            "environment" => ENVIRONMENT,
            "csrfToken" => csrf_token(),
            "csrfHash" => csrf_hash(),
        ];
        $this->data['hyper']['lang'] = dump_language_keys_grouped();

        $this->data['title'] = config(Hyper::class)->appName;
        $this->data['locale'] = service('request')->getLocale();
        $this->data['uri'] = $request->getUri() . '/';
        $this->data['uriSegments'] = $request->getUri()->getSegments();

        $this->data['models'] = $this->modelsModel->getCustomBuilder()->get()->getResultArray();

        /* View data */

        $menu = [
            [
                'url' => base_url('admin/dashboard'),
                'icon' => 'fa-solid fa-house',
                'text' => lang("Admin.dashboard"),
                'tooltip_content' => lang("Admin.dashboard"),
                'tooltip_placement' => 'right',
            ],
            [
                'url' => base_url('admin/models'),
                'icon' => 'fa-solid fa-circle-nodes',
                'text' => lang("Admin.models"),
                'tooltip_content' => lang("Admin.models"),
                'tooltip_placement' => 'right',
            ],
            [
                'url' => base_url('admin/entries'),
                'icon' => 'fa-solid fa-table-list',
                'text' => lang("Admin.entries"),
                'tooltip_content' => lang("Admin.entries"),
                'tooltip_placement' => 'right',
            ],
            [
                'url' => base_url('admin/file-manager'),
                'icon' => 'fa-solid fa-folder-closed',
                'text' => lang("Admin.fileManager"),
                'tooltip_content' => lang("Admin.fileManager"),
                'tooltip_placement' => 'right',
            ]
        ];

        $additionalMenu = [];
        $additionalMenu = $this->hooks->filter(hook('Backend.controller:menu:data'), $additionalMenu);

        $this->data['menu'] = array_merge($menu, $additionalMenu);

        /* End of view data */

        /* Testing */

        $syntaxProcessor = new SyntaxProcessor();
        if (false):
            log_message('debug', json_encode($syntaxProcessor->process('
            [
                {
                    "type": "data",
                    "content": "hooks"
                    },
                    {
                        "type": "data",
                        "content": {
                            "table": "entries",
                            "select": "id as value, fields as label",
                            "orderby": "id ASC"
                            }
                    }
            ]
            ')));
        endif;

        /* End of testing */
    }

    protected function respond(
        string $message,
        ?string $redirectTo = null,
        int $statusCode = 200,
        bool $withInput = true,
        bool $success = true
    ) {
        if (
            $this->request->isAJAX() ||
            strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false ||
            strpos($this->request->getHeaderLine('Content-Type'), 'application/json') !== false
        ) {
            return $this->response->setStatusCode($statusCode)->setJSON([$success ? 'success' : 'error' => $message]);
        }

        /** @var \CodeIgniter\HTTP\RedirectResponse */
        $redirect = redirect();

        if (empty($redirectTo)) {
            $redirect = $redirect->back();
        } else {
            $redirect = $redirect->to($redirectTo);
        }

        if ($withInput) {
            $redirect = $redirect->withInput();
        }

        $redirect = $redirect->with($success ? 'success' : 'error', $message);

        return $redirect;
    }
}
