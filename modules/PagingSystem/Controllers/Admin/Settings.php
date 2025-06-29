<?php

namespace PagingSystem\Controllers\Admin;

use App\Controllers\AdminController;
use App\Services\HyperHooks;
use App\Services\ModelsManager;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Settings extends AdminController
{

    protected ModelsManager $modelsManager;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        /** @var ModelsManager */
        $this->modelsManager = service('ModelsManager');
    }

    public function index()
    {
        log_message('debug', 'Paging System: Settings hook triggered.');

        $setup = $this->request->getGet('setup') === 'true';

        /** @var array $pagingSystemEligibleModelIds */
        $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');
        /** @var array $pagingSystemEligibleModelNames */
        $pagingSystemEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_eligible_model_names');
        // Whether the eligible model exists
        $eligibleModelExists = count($pagingSystemEligibleModelIds) > 0;

        log_message('debug', 'Paging System: Editor-eligible model IDs: ' . implode(', ', $pagingSystemEligibleModelIds));
        log_message('debug', 'Paging System: Editor-eligible model names: ' . implode(', ', $pagingSystemEligibleModelNames));

        /** @var array $pagingSystemAssetsEligibleModelIds */
        $pagingSystemAssetsEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_assets_eligible_model_ids');
        /** @var array $pagingSystemAssetsEligibleModelNames */
        $pagingSystemAssetsEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_assets_eligible_model_names');
        // Whether the eligible model exists
        $eligibleAssetsModelExists = count($pagingSystemAssetsEligibleModelIds) > 0;

        log_message('debug', 'Paging System: Assets-eligible model IDs: ' . implode(', ', $pagingSystemAssetsEligibleModelIds));
        log_message('debug', 'Paging System: Assets-eligible model names: ' . implode(', ', $pagingSystemAssetsEligibleModelNames));

        /** @var array $pagingSystemMetaEligibleModelIds */
        $pagingSystemMetaEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_meta_eligible_model_ids');
        /** @var array $pagingSystemMetaEligibleModelNames */
        $pagingSystemMetaEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_meta_eligible_model_names');
        // Whether the eligible model exists
        $eligibleMetaModelExists = count($pagingSystemMetaEligibleModelIds) > 0;

        log_message('debug', 'Paging System: Meta-eligible model IDs: ' . implode(', ', $pagingSystemMetaEligibleModelIds));
        log_message('debug', 'Paging System: Meta-eligible model names: ' . implode(', ', $pagingSystemMetaEligibleModelNames));

        // Run setup if necessary
        if ($setup && (!$eligibleModelExists || !$eligibleAssetsModelExists)) {
            $this->setup($eligibleModelExists, $eligibleAssetsModelExists);
            return $this->respond(lang('PagingSystem.setUpModelsSuccessfully'), base_url("admin/settings/paging-system"));
        }

        $this->data['title'] = lang('PagingSystem.moduleName');

        return render('Modules\PagingSystem\Views\Admin\settings', array_merge($this->data, [
            'pagingSystemEligibleModelIds' => $pagingSystemEligibleModelIds,
            'pagingSystemEligibleModelNames' => $pagingSystemEligibleModelNames,
            'eligibleModelExists' => $eligibleModelExists,
            'pagingSystemAssetsEligibleModelIds' => $pagingSystemAssetsEligibleModelIds,
            'pagingSystemAssetsEligibleModelNames' => $pagingSystemAssetsEligibleModelNames,
            'eligibleAssetsModelExists' => $eligibleAssetsModelExists,
            'pagingSystemMetaEligibleModelIds' => $pagingSystemMetaEligibleModelIds,
            'pagingSystemMetaEligibleModelNames' => $pagingSystemMetaEligibleModelNames,
            'eligibleMetaModelExists' => $eligibleMetaModelExists,
        ]));
    }

    protected function setup($eligibleModelExists, $eligibleAssetsModelExists)
    {
        // Create a Page model if it doesn’t already exist
        if (!$eligibleModelExists) {
            $this->modelsManager->create(
                [
                    'name' => lang('PagingSystem.page'),
                    'group' => lang('PagingSystem.page'),
                    'user_groups' => json_encode(["superadmin"]),
                    'icon' => 'fas fa-file',
                    'fields' => json_encode([
                        [
                            "id" => "hyper_title",
                            "label" => "Title",
                            "type" => "text",
                            "helper" => "Used for display purposes. When the attachment is set to <b>Main Frontend</b>, this title will serve as the page title.",
                            "required" => true,
                        ],
                        [
                            "id" => "hyper_page_hook_id",
                            "label" => "Part attachment",
                            "type" => "select",
                            "helper" => "The attachment determines where the part is rendered. To create a page, select <b>Main Frontend</b>.",
                            "options" => [
                                "type" => "data",
                                "content" => "hooks",
                                "group" => "Frontend",
                            ],
                        ],
                        [
                            "id" => "hyper_page_url",
                            "label" => "Url",
                            "type" => "text",
                            "helper" => "For <b>Main Frontend</b> attachment: Provide the relative URL of the page. For example, type <i>products</i> instead of <i>[base_url]products</i>.",
                        ],
                        [
                            "id" => "hyper_html",
                            "label" => "HTML",
                            "type" => "textarea",
                            "className" => "hyper-code-field",
                            "helper" => "Write your part's HTML code here or use Editor.",
                        ],
                        [
                            "id" => "hyper_css",
                            "label" => "CSS",
                            "type" => "textarea",
                            "className" => "hyper-code-field",
                            "helper" => "Write your part's CSS code here or use Editor.",
                        ],
                        [
                            "id" => "hyper_component_elements",
                            "label" => "Component elements",
                            "type" => "hidden",
                            "className" => "hyper-code-field",
                            "helper" => "Will be filled automatically by the <b>Editor</b>.",
                        ],
                        [
                            "id" => "hyper_page_project_data",
                            "label" => "Project data",
                            "type" => "hidden",
                            "className" => "hyper-code-field",
                            "helper" => "Will be filled automatically by the <b>Editor</b>.",
                        ],
                    ]),
                ],
                auth()->user()->id
            );
        }

        // Create an Assets model if it doesn’t already exist
        if (!$eligibleAssetsModelExists) {
            $this->modelsManager->create(
                [
                    'name' => lang('PagingSystem.assets'),
                    'group' => lang('PagingSystem.page'),
                    'user_groups' => json_encode(["superadmin"]),
                    'icon' => 'fas fa-file-code',
                    'fields' => json_encode([
                        [
                            'id'        => 'asset_label',
                            'label'     => 'Label',
                            'type'      => 'text',
                            'helper'    => 'Optional display name for the asset',
                        ],
                        [
                            'id'        => 'asset_url',
                            'label'     => 'URL',
                            'type'      => 'url',
                            'required'  => true,
                            'className' => 'hyper-file-browse-field',
                            'helper'    => 'Select file using the asset manager',
                        ],
                        [
                            'id'        => 'asset_type',
                            'label'     => 'Type',
                            'type'      => 'select',
                            'value'     => 'script',
                            'helper'    => 'Script for JavaScript, Style for CSS',
                            'options'   => [
                                [
                                    'value'    => 'script',
                                    'label'    => 'Script',
                                    'selected' => '',
                                ],
                                [
                                    'value' => 'style',
                                    'label' => 'Style',
                                ],
                            ],
                        ],
                        [
                            'id'        => 'asset_placement',
                            'label'     => 'Placement',
                            'type'      => 'select',
                            'value'     => 'head',
                            'helper'    => 'Where to load the asset in HTML',
                            'options'   => [
                                [
                                    'value'    => 'head',
                                    'label'    => 'Head',
                                    'selected' => '',
                                ],
                                [
                                    'value' => 'body',
                                    'label' => 'Body',
                                ],
                            ],
                        ],
                    ]),
                ],
                auth()->user()->id
            );
        }
    }

    public function update()
    {
        $data = $this->request->getPost();

        $rules = [
            'paging_system_primary_model_id' => [
                'label' => lang('PagingSystem.primary'),
                'rules' => 'required',
            ],
            'paging_system_assets_model_id' => [
                'label' => lang('PagingSystem.assets'),
                'rules' => 'required',
            ],
            'paging_system_meta_model_id' => [
                'label' => lang('PagingSystem.meta'),
                'rules' => 'required',
            ],
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->respond(implode(" ", $this->validator->getErrors()), withInput: true, success: false);
        }

        service('settings')->set('PagingSystem.primaryModelId', $data['paging_system_primary_model_id']);
        service('settings')->set('PagingSystem.assetsModelId', $data['paging_system_assets_model_id']);
        service('settings')->set('PagingSystem.metaModelId', $data['paging_system_meta_model_id']);

        return $this->respond(lang('Admin.settingsSuccessfullySaved'), 'admin/settings/paging-system');
    }
}
