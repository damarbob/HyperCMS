<?php

namespace App\Controllers;

use App\Libraries\SyntaxProcessor;
use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use App\Models\ModelDataModel;
use App\Models\ModelsModel;
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

        // Models
        $this->modelsModel = new ModelsModel();
        $this->modelDataModel = new ModelDataModel();
        $this->entriesModel = new EntriesModel();
        $this->entryDataModel = new EntryDataModel();

        // Preinit data
        $this->data['title'] = (new Hyper)->appName;
        $this->data['lang'] = service('request')->getLocale();
        $this->data['uri'] = $request->getUri() . '/';
        $this->data['uriSegments'] = $request->getUri()->getSegments();

        $this->data['models'] = $this->modelsModel->getCustomBuilder()->get()->getResultArray();

        // log_message('debug', implode(',', $this->data['uriSegments']));

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
    }
}
