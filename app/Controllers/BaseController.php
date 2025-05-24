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

        // --- Preinit data ---
        $models = $this->modelsModel->getCustomBuilder()->get()->getResultArray();

        // Build the menu as a nested array
        $menu = [
            lang('Admin.general') => [
                'dashboard' => [
                    'url'              => base_url('admin/dashboard'),
                    'icon'             => 'fa-solid fa-house',
                    'text'             => lang("Admin.dashboard"),
                    'tooltip_content'  => lang("Admin.dashboard"),
                    'tooltip_placement' => 'right'
                ],
                'models' => [
                    'url'              => base_url('admin/models'),
                    'icon'             => 'fa-solid fa-circle-nodes',
                    'text'             => lang("Admin.models"),
                    'tooltip_content'  => lang("Admin.models"),
                    'tooltip_placement' => 'right'
                ],
                'entries' => [
                    'url'              => base_url('admin/entries'),
                    'icon'             => 'fa-solid fa-table-list',
                    'text'             => lang("Admin.entries"),
                    'tooltip_content'  => lang("Admin.entries"),
                    'tooltip_placement' => 'right'
                ],
                'file-manager' => [
                    'url'              => base_url('admin/file-manager'),
                    'icon'             => 'fa-solid fa-folder-closed',
                    'text'             => lang("Admin.fileManager"),
                    'tooltip_content'  => lang("Admin.fileManager"),
                    'tooltip_placement' => 'right'
                ],
            ],
            // This group contains a submenu (nested items)
            lang('Admin.others') => [
                'settings' => [
                    // The parent link acting as a container (URL may be "#" or a clickable parent)
                    'url'              => base_url('admin/settings'),
                    'icon'             => 'fa-solid fa-cog',
                    'text'             => lang("Admin.settings"),
                    'tooltip_content'  => lang("Admin.settings"),
                    'tooltip_placement' => 'right',
                    'submenu' => [
                        'settings-general' => [
                            'url'              => base_url('admin/settings'),
                            'text'             => lang("Admin.general"),
                            'tooltip_content'  => lang("Admin.general"),
                            'tooltip_placement' => 'right'
                        ],
                        'settings-models' => [
                            'url'              => base_url('admin/settings/models'),
                            'text'             => lang("Admin.models"),
                            'tooltip_content'  => lang("Admin.models"),
                            'tooltip_placement' => 'right'
                        ],
                        // You can also add additional submenu items or merge hook-driven items.
                    ]
                ]
            ]
        ];

        $modelsMenu = [];
        foreach ($models as $model) {
            $groupName = strtoupper(empty($model['group']) ? lang('Admin.models') : $model['group']);
            $modelsMenu[$groupName]["model-{$model['id']}"] = [
                'url' => base_url('admin/model/' . $model['id']),
                'text' => $model['name'],
                'icon' => $model['icon'],
                'tooltip_content'  => $model['name'],
                'tooltip_placement' => 'right'
            ];
        }

        // Merge menu with modelsMenu
        $menu = array_merge($menu, $modelsMenu);

        // Additional menu for filter (to separate from the main menu)
        $additionalMenu = $this->hooks->filter(hook('Backend.controller:menu:data'), []);

        // Merge menu with additionalMenu
        $menu = array_merge_recursive($menu, $additionalMenu);

        // Menu reordering
        $menu = $this->reorderMenuByKey($menu, lang('Admin.general'), 'start'); // Move the 'General' to the start
        $menu = $this->reorderMenuByKey($menu, lang('Admin.others'), 'end'); // Move the 'Others' key to the end
        $menu = $this->reorderMenuByKey($menu, lang('Admin.ai'), 1); // Move the 'AI' key to position 1 (second position)
        $menu = $this->reorderMenuItemByIdAndKey($menu, lang('Admin.others'), 'settings', 'end'); // Always place settings menu at the very end

        // dd($menu);

        /* View data */

        $this->data = [
            'hyper' => [
                'config' => [
                    "baseUrl"       => base_url(),
                    "environment"   => ENVIRONMENT,
                    "csrfHeader"    => csrf_header(),
                    "csrfToken"     => csrf_token(),
                    "csrfHash"      => csrf_hash(),
                    "locale"        => service('request')->getLocale(),
                ],
                'lang' => dump_language_keys_grouped(),
            ],
            'title'       => config(Hyper::class)->appName,
            'locale'      => service('request')->getLocale(),
            'uri'         => $request->getUri() . '/',
            'uriSegments' => $request->getUri()->getSegments(),
            'models'      => $models,
            'menu'        => $menu,
        ];

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

    /**
     * Reorders an associative array so that the element with the specified key is moved to a new position.
     *
     * @param array  $menu      The original menu array.
     * @param string $groupKey  The key to reposition.
     * @param mixed  $position  The new position: 
     *                          - Use 'start' to move the key to the beginning.
     *                          - Use 'end' to move the key to the end.
     *                          - Use an integer to insert the key at that (zero-based) index.
     *
     * @return array The reordered array.
     */
    public static function reorderMenuByKey(array $menu, string $groupKey, $position): array
    {
        // If the target key is not present, return the original array.
        if (!array_key_exists($groupKey, $menu)) {
            return $menu;
        }

        // Remove the element from its current position.
        $element = $menu[$groupKey];
        unset($menu[$groupKey]);

        // Determine where to insert the element.
        if ($position === 'start') {
            // Insert at the beginning.
            return array_merge([$groupKey => $element], $menu);
        } elseif ($position === 'end') {
            // Insert at the end.
            return array_merge($menu, [$groupKey => $element]);
        } elseif (is_numeric($position)) {
            // Make sure position is an integer (zero-based index).
            $position = (int)$position;
            // If the position is zero or less, treat it as 'start'.
            if ($position <= 0) {
                return array_merge([$groupKey => $element], $menu);
            }
            $reordered = [];
            $i = 0;
            // Insert the element at the specified numeric position.
            foreach ($menu as $key => $value) {
                if ($i === $position) {
                    $reordered[$groupKey] = $element;
                }
                $reordered[$key] = $value;
                $i++;
            }
            // If the requested position is beyond the end, append the element.
            if ($position >= $i) {
                $reordered[$groupKey] = $element;
            }
            return $reordered;
        }

        // If none of the valid conditions match, return original.
        return $menu;
    }

    /**
     * Reorders a menu item within a given group based on the item’s ID (the array key).
     *
     * The menu is structured as:
     *   [ groupKey => [ itemId => itemData, ... ] ]
     *
     * @param array  $menu      The entire menu array.
     * @param string $groupKey  The key for the menu group to target.
     * @param string $targetId  The ID key of the item to reposition.
     * @param mixed  $position  The new position in the group:
     *                          - 'start' to move the item at the beginning,
     *                          - 'end'   to move it at the end,
     *                          - Or a numeric index (zero-based) to insert at a specific position.
     *
     * @return array The updated menu array.
     */
    function reorderMenuItemByIdAndKey(array $menu, string $groupKey, string $targetId, $position = 'end'): array
    {
        // Ensure the group exists and is an array.
        if (!isset($menu[$groupKey]) || !is_array($menu[$groupKey])) {
            return $menu;
        }

        $items = $menu[$groupKey];
        // Check if the target key exists.
        if (!array_key_exists($targetId, $items)) {
            return $menu;
        }

        // Extract the target element while preserving its key.
        $element = [$targetId => $items[$targetId]];
        // Remove it from the original array.
        unset($items[$targetId]);

        // Reinsert based on the desired position.
        if ($position === 'start') {
            // Insert at the beginning: merge with $element first.
            $items = $element + $items;
        } elseif ($position === 'end') {
            // Append: merge at the end.
            $items = $items + $element;
        } elseif (is_numeric($position)) {
            $position = (int)$position;
            // Use array_slice to preserve keys.
            $begin = array_slice($items, 0, $position, true);
            $end   = array_slice($items, $position, null, true);
            $items = $begin + $element + $end;
        }

        // Save the reordered group back into the menu.
        $menu[$groupKey] = $items;
        return $menu;
    }
}
