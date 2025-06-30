<?php

namespace Voltic\Controllers;

use Exception;
use App\Controllers\AdminController;
use CodeIgniter\Shield\Exceptions\PermissionException;
use Voltic\Libraries\VolticService;

/**
 * Controller to handle the Voltic chat module in the admin area.
 */
class Voltic extends AdminController
{
    // Load Hyper URL, URL, and Form helpers for view utilities.
    protected $helpers = ['hyper_url', 'url', 'form'];

    // VolticService instance for handling AI requests.
    protected VolticService $voltic;

    // Instantiate the VolticService library.
    public function __construct()
    {
        $this->voltic = new VolticService();
    }

    // Show the main Voltic chat interface.
    public function index()
    {
        $this->data['title']    = lang('Voltic.moduleName'); // Set the page title from language file.
        $this->data['username'] = auth()->user()->username; // Fetch the authenticated user's username.

        return render('\Voltic\Views\voltic', $this->data); // Render the view template with data.
    }

    public function ask()
    {
        /** @var \Voltic\Config\Voltic */
        $config = config('Voltic');

        /** @var \App\Services\ModelsManager */
        $modelsManager = service('modelsManager');

        /** @var \App\Services\EntriesManager */
        $entriesManager = service('entriesManager');

        // Get the current user
        $user = auth()->user();

        $prompt = $this->request->getJSON();

        if (empty($prompt)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => lang('Voltic.messageCannotBeEmpty')
            ]);
        }

        $response = $this->voltic->ask($prompt);

        try {
            // Action processing
            if (!empty($response['actions'])) {
                // if (!is_array($response['actions'])) $response['actions'] = [$response['actions']];
                foreach ($response['actions'] as $x) {

                    try {

                        $transformParams = function ($action) {
                            // Extract the 'params' array from the first element.
                            $params = $action['params'];

                            // If the params is already reduced (associative), simply return it.
                            if (!isset($params[0])) {
                                log_message('debug', $action['type'] . " params already reduced: " . json_encode($params));
                                return $params;
                            }

                            // Transform the params array into the desired associative array
                            $output = array_reduce($params, function ($carry, $item) {
                                // If the id is 'field', use 'fields' as key; otherwise, use the original id.
                                if ($item['id'] === 'field') {
                                    $carry['fields'] = $item['value'];
                                } else {
                                    $value = $item['value'];
                                    $carry[$item['id']] = $value;
                                }
                                return $carry;
                            }, []);

                            log_message('debug', $action['type'] . " params: " . json_encode($output));

                            return $output;
                        };

                        switch ($x['type']) {
                            case "create_model":

                                // Only superadmin allowed to create models
                                if (!$user->inGroup('superadmin')) {
                                    throw new PermissionException(lang('Voltic.userDoesNotHaveSufficientPermission'));
                                }

                                // Convert fields to string to insert into the database
                                $insertData = $transformParams($x);
                                $insertData['fields'] = json_encode($insertData['fields']);

                                // Delegate the creation process to the service.
                                $modelId = $modelsManager->create($insertData, auth()->user()->id);
                                $response['system'][] =
                                    [
                                        "message" => lang('Voltic.modelxCreatedSuccessfully', ['x' => $insertData['name']]),
                                        "actions" => [
                                            [
                                                "type" => "button",
                                                "text" => lang('Admin.editx', ['x' => lang('Admin.model')]),
                                                "icon" => "fa-solid fa-pen-to-square",
                                                "href" => base_url("admin/models/$modelId/edit")
                                            ],
                                            [
                                                "type" => "button",
                                                "text" => lang('Admin.newx', ['x' => lang('Admin.entry')]),
                                                "icon" => "fas fa-plus",
                                                "href" => base_url("admin/entries/$modelId/new")
                                            ],
                                        ],
                                    ];
                                break;
                            case "create_entry":

                                // User and beta are not allowed to create entry
                                if ($user->inGroup('user', 'beta')) {
                                    throw new PermissionException(lang('Voltic.userDoesNotHaveSufficientPermission'));
                                }

                                // Convert fields to string to insert into the database
                                $insertData = $transformParams($x);
                                $insertData['fields'] = json_encode($insertData['fields']);

                                // Delegate the creation process to the service.
                                $entryId = $entriesManager->create($insertData, auth()->user()->id);
                                $modelId = $entriesManager->find($entryId)['model_id'];
                                $model = $modelsManager->find($modelId);

                                $response['system'][] =
                                    [
                                        "message" => lang('Voltic.entryxCreatedSuccessfully', ['x' => $model['name']]),
                                        "actions" => [
                                            [
                                                "type" => "button",
                                                "text" => lang('Admin.editx', ['x' => lang('Admin.entry')]),
                                                "icon" => "fa-solid fa-pen-to-square",
                                                "href" => base_url("admin/entries/$modelId/$entryId/edit")
                                            ],
                                        ],
                                    ];
                                break;
                            case "show_entry":

                                $params = $x['params'];
                                $params = $params;

                                $entryId = $params['id'];

                                // Delegate the creation process to the service.
                                $entry = $entriesManager->find($entryId);
                                $modelId = $entry['model_id'];
                                $model   = $modelsManager->find($modelId);

                                $fields = map_entry_fields($entry['fields']);

                                // Convert the entry into a Markdown table.
                                // Create header: two columns 'Field' and 'Value'
                                $table  = "| Field | Value |\n";
                                $table .= "|-------|-------|\n";

                                // Iterate through each key/value in the $entry array.
                                foreach ($fields as $key => $value) {
                                    // If the value is an array or object, encode it as JSON.
                                    if (is_array($value) || is_object($value)) {
                                        $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                    }
                                    // Display nulls as a string.
                                    if (is_null($value)) {
                                        $value = 'null';
                                    }
                                    // Trim any extra whitespace.
                                    $value = trim($value);
                                    // Replace newline characters with HTML <br> tags so the table cell remains on one line.
                                    $value = str_replace("\n", "<br>", $value);

                                    // Append a row to the table. You can adjust the formatting as needed.
                                    $table .= "| **{$key}** | {$value} |\n";
                                }

                                $entryMarkdown = $table;

                                $msg = lang('Voltic.showingEntryx', ['x' => $model['name']]);

                                $response['system'][] = [
                                    "message" => <<<EOL
                                    #{$msg}

                                    {$entryMarkdown}
                                    EOL,
                                    "actions" => [
                                        [
                                            "type" => "button",
                                            "text" => lang('Admin.editx', ['x' => lang('Admin.entry')]),
                                            "icon" => "fa-solid fa-pen-to-square",
                                            "href" => base_url("admin/entries/$modelId/$entryId/edit")
                                        ],
                                    ],
                                ];

                                break;
                        }
                    } catch (\Throwable $e) {

                        // Append error object to the response
                        $response['error'] = [
                            'message' => $e->getMessage(),
                        ];

                        // Append error trace on environment other than production
                        if (ENVIRONMENT !== 'production') {
                            $response['error']['trace'] = $e->getTraceAsString();
                        }
                    }
                }
            }
        } catch (Exception $e) {

            // Append error object to the response
            $response['error'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        if (!$response) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => lang('Voltic.failedToGetResponseFromVoltic'),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => $response
        ]);
    }
}
