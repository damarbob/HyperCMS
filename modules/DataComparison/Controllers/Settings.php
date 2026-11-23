<?php

namespace DataComparison\Controllers;

use App\Modules\ModuleController;

/**
 * The data sources of the person. A source is a name and the request that fetches the fields of an entry from it, kept as
 * JSON, so a person can point the table at another site.
 */
class Settings extends ModuleController
{
    protected $helpers = ['hyper_url'];

    public function index()
    {
        $json = service('settings')->get('DataComparison.dataSources', 'user:' . user_id());

        return $this->form(is_string($json) && $json !== '' ? $json : $this->defaults());
    }

    public function update()
    {
        $text = (string) $this->request->getPost('dataSources');
        $list = json_decode($text, true);
        if (! is_array($list) || ($list !== [] && ! array_is_list($list))) {
            return $this->form($text, lang('Dc.dataSourcesMustBeAList'), 422);
        }
        $seen = [];
        foreach ($list as $source) {
            $ok = is_array($source) && is_string($source['id'] ?? null) && $source['id'] !== '' && is_string($source['label'] ?? null)
                && is_string($source['options']['url'] ?? null) && $source['options']['url'] !== '' && ! isset($seen[$source['id']]);
            if (! $ok) {
                return $this->form($text, lang('Dc.dataSourceNeedsFields'), 422);
            }
            $seen[$source['id']] = true;
        }

        service('settings')->set('DataComparison.dataSources', json_encode($list, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 'user:' . user_id());

        return $this->leave(base_url('admin/settings/data-comparison'), lang('Admin.settingsSuccessfullySaved'), 'ok');
    }

    private function defaults(): string
    {
        /** @var \DataComparison\Config\DataComparison $config */
        $config  = config('DataComparison');
        $decoded = json_decode(trim($config->defaultDataSources ?? ''), true);

        return json_encode(is_array($decoded) ? $decoded : [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function form(string $text, string $error = '', int $status = 200)
    {
        return $this->response->setStatusCode($status)->setBody($this->page('DataComparison\Views\settings', [
            'title'       => lang('Dc.moduleName') . ' ' . lang('Admin.settings'),
            'dataSources' => $text,
            'error'       => $error,
        ]));
    }
}
