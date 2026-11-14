<?php

namespace App\Controllers;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use StarCore\Service\HyperHooks;
use StarDust\Models\EntriesModel;
use StarDust\Models\EntryDataModel;
use StarDust\Models\ModelDataModel;
use StarDust\Models\ModelsModel;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 * class Home extends BaseController
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
    protected $session;
    protected HyperHooks $hooks;

    // Data to be passed to views
    protected array $data;

    /**
     * Properties that every controller used to have and that now live in ModelStore and EntryStore.
     * Custom controllers and modules may still read them, so they are served on demand.
     * A name maps to the service it used to hold, or to the model class when it holds a model.
     *
     * @var array<string, string>
     */
    private const LEGACY_SERVICES = [
        'modelsManager' => 'modelsManager',
        'entriesManager' => 'entriesManager',
    ];

    /** @var array<string, class-string> */
    private const LEGACY_MODELS = [
        'modelsModel' => ModelsModel::class,
        'modelDataModel' => ModelDataModel::class,
        'entriesModel' => EntriesModel::class,
        'entryDataModel' => EntryDataModel::class,
    ];

    /**
     * Names already reported in this request, so a loop does not fill the log.
     *
     * @var array<string, true>
     */
    private static array $legacyReported = [];

    /**
     * Serve a removed StarDust property, for example `$this->entriesManager->find(5)` in a custom module.
     *
     * @deprecated Use service('modelStore') and service('entryStore') instead.
     */
    public function __get(string $name)
    {
        if (!$this->__isset($name)) {
            trigger_error('Undefined property: ' . static::class . '::$' . $name, E_USER_WARNING);

            return null;
        }

        if (!isset(self::$legacyReported[$name])) {
            self::$legacyReported[$name] = true;
            log_message('warning', static::class . '::$' . $name . " is deprecated. Use service('modelStore') or service('entryStore') instead.");
        }

        return isset(self::LEGACY_SERVICES[$name])
            ? service(self::LEGACY_SERVICES[$name])
            : model(self::LEGACY_MODELS[$name]);
    }

    public function __isset(string $name): bool
    {
        return isset(self::LEGACY_SERVICES[$name]) || isset(self::LEGACY_MODELS[$name]);
    }

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        $this->session = service('session');

        $this->hooks = service('hooks');

        /**
         * Store the URL history in the session on every non-AJAX,
         * GET request that expects an HTML response.
         * This "rotates" the history stack.
         */
        if (
            $this->request->is('get') &&
            !$this->request->isAJAX() &&
            strpos($this->request->getHeaderLine('Accept'), 'text/html') !== false
        ) {
            // Get the full URL *with* query string, but no #fragment
            $uri = $this->request->getUri();
            $currentUrlWithQuery = (string) $uri->setFragment('');

            $savedCurrent = $this->session->get('_history_current');

            // Only rotate if the URL has actually changed
            if ($savedCurrent !== $currentUrlWithQuery) {
                // @TODO: Consider expanding to a deeper history stack. 
                // Unused for now, but may be useful later
                // Move the 'current' to 'previous'
                $this->session->set('_history_previous', $savedCurrent);
                // Set the new 'current'
                $this->session->set('_history_current', $currentUrlWithQuery);
            }
        }
    }

    /**
     * Respond to a request with either a JSON or a redirect response.
     *
     * @param string      $message    The message to return.
     * @param string|null $redirectTo The URL to redirect to; if null, will redirect back.
     * @param int         $statusCode The HTTP status code (default 200).
     * @param bool        $withInput  Whether to carry the input data.
     * @param bool        $success    Determines the message key ('success' or 'error').
     * * @return \CodeIgniter\HTTP\ResponseInterface
     */
    protected function respond(
        string $message,
        ?string $redirectTo = null,
        int $statusCode = 200,
        bool $withInput = true,
        bool $success = true
    ): \CodeIgniter\HTTP\ResponseInterface {
        if (
            $this->request->isAJAX() ||
            strpos($this->request->getHeaderLine('Accept'), 'application/json') !== false ||
            strpos($this->request->getHeaderLine('Content-Type'), 'application/json') !== false
        ) {
            return $this->response
                ->setStatusCode($statusCode)
                ->setContentType('application/json')
                ->setJSON([$success ? 'success' : 'error' => $message]);
        }

        $redirectUrl = $redirectTo; // Start with the explicitly passed URL

        if (empty($redirectUrl)) {
            // No explicit URL, so we figure out "back"

            if ($this->request->is('get')) {
                // IT'S A GET REQUEST! This is the redirect loop scenario.
                // We CANNOT use the session, as it would loop.
                // We MUST redirect to a safe fallback page.
                // Use dashboard for logged-in users, home for guests.
                $redirectUrl = auth()->user() ? route_to('dashboard') : site_url('/');
            } else {
                // IT'S A POST/PUT/ETC. This is the normal form submission.
                // Use the 'current' history URL (the page that had the form).
                $redirectUrl = session('_history_current') ?? site_url('/');
            }
        }

        $redirect = redirect()->to($redirectUrl);

        if ($withInput) {
            $redirect->withInput();
        }

        return $redirect->with($success ? 'success' : 'error', $message);
    }
}
