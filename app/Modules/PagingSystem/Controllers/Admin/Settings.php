<?php

namespace Modules\PagingSystem\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\HyperHooks;

class Settings extends BaseController
{
    public function index()
    {
        $pagingSystemEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_eligible_model_ids');
        $pagingSystemEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_eligible_model_names');
        $pagingSystemMetaEligibleModelIds = HyperHooks::getInstance()->getState('paging_system_meta_eligible_model_ids');
        $pagingSystemMetaEligibleModelNames = HyperHooks::getInstance()->getState('paging_system_meta_eligible_model_names');
        log_message('debug', 'Paging System settings hook triggered.');
        log_message('debug', 'Paging System eligible model IDs: ' . implode(', ', $pagingSystemEligibleModelIds));
        log_message('debug', 'Paging System eligible model names: ' . implode(', ', $pagingSystemEligibleModelNames));
        log_message('debug', 'Paging System meta eligible model IDs: ' . implode(', ', $pagingSystemMetaEligibleModelIds));
        log_message('debug', 'Paging System meta eligible model names: ' . implode(', ', $pagingSystemMetaEligibleModelNames));

        $this->data['title'] = lang('PagingSystem.moduleName');

        return view('Modules\PagingSystem\Views\Admin\settings', array_merge($this->data, [
            'pagingSystemEligibleModelIds' => $pagingSystemEligibleModelIds,
            'pagingSystemEligibleModelNames' => $pagingSystemEligibleModelNames,
            'pagingSystemMetaEligibleModelIds' => $pagingSystemMetaEligibleModelIds,
            'pagingSystemMetaEligibleModelNames' => $pagingSystemMetaEligibleModelNames,
        ]));
    }

    public function update()
    {
        $data = $this->request->getPost();

        /** @var \CodeIgniter\Validation\ValidationInterface */
        $validation = service('validation');

        $validation->setRules([
            'paging_system_primary_model_id' => [
                'label' => lang('PagingSystem.primary'),
                'rules' => 'required',
            ],
            'paging_system_meta_model_id' => [
                'label' => lang('PagingSystem.meta'),
                'rules' => 'required',
            ],
        ]);

        if (!$validation->run($data)) {
            return redirect()->back()->withInput();
        }

        service('settings')->set('PagingSystem.primaryModelId', $data['paging_system_primary_model_id']);
        service('settings')->set('PagingSystem.metaModelId', $data['paging_system_meta_model_id']);

        return redirect('admin/settings/paging-system')->with('success', lang('Admin.settingsSuccessfullySaved'));
    }
}
