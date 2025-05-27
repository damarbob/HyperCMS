<?php

namespace PagingSystem\Controllers\Admin;

use App\Controllers\AdminController;
use App\Services\HyperHooks;

class Settings extends AdminController
{
    public function index()
    {
        log_message('debug', 'Paging System: Settings hook triggered.');

        $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');
        $pagingSystemEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_eligible_model_names');
        log_message('debug', 'Paging System: Editor-eligible model IDs: ' . implode(', ', $pagingSystemEligibleModelIds));
        log_message('debug', 'Paging System: Editor-eligible model names: ' . implode(', ', $pagingSystemEligibleModelNames));

        $pagingSystemAssetsEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_assets_eligible_model_ids');
        $pagingSystemAssetsEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_assets_eligible_model_names');
        log_message('debug', 'Paging System: Assets-eligible model IDs: ' . implode(', ', $pagingSystemAssetsEligibleModelIds));
        log_message('debug', 'Paging System: Assets-eligible model names: ' . implode(', ', $pagingSystemAssetsEligibleModelNames));

        $pagingSystemMetaEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_meta_eligible_model_ids');
        $pagingSystemMetaEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_meta_eligible_model_names');
        log_message('debug', 'Paging System: Meta-eligible model IDs: ' . implode(', ', $pagingSystemMetaEligibleModelIds));
        log_message('debug', 'Paging System: Meta-eligible model names: ' . implode(', ', $pagingSystemMetaEligibleModelNames));

        $this->data['title'] = lang('PagingSystem.moduleName');

        return render('Modules\PagingSystem\Views\Admin\settings', array_merge($this->data, [
            'pagingSystemEligibleModelIds' => $pagingSystemEligibleModelIds,
            'pagingSystemEligibleModelNames' => $pagingSystemEligibleModelNames,
            'pagingSystemAssetsEligibleModelIds' => $pagingSystemAssetsEligibleModelIds,
            'pagingSystemAssetsEligibleModelNames' => $pagingSystemAssetsEligibleModelNames,
            'pagingSystemMetaEligibleModelIds' => $pagingSystemMetaEligibleModelIds,
            'pagingSystemMetaEligibleModelNames' => $pagingSystemMetaEligibleModelNames,
        ]));
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
            // return redirect()->back()->withInput();
        }

        service('settings')->set('PagingSystem.primaryModelId', $data['paging_system_primary_model_id']);
        service('settings')->set('PagingSystem.assetsModelId', $data['paging_system_assets_model_id']);
        service('settings')->set('PagingSystem.metaModelId', $data['paging_system_meta_model_id']);

        return $this->respond(lang('Admin.settingsSuccessfullySaved'), 'admin/settings/paging-system');
        // return redirect('admin/settings/paging-system')->with('success', lang('Admin.settingsSuccessfullySaved'));
    }
}
