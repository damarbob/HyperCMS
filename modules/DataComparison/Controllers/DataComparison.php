<?php

namespace DataComparison\Controllers;

use App\Controllers\AdminController;

class DataComparison extends AdminController
{
    protected $helpers = ['hyper', 'hyper_url'];

    public function index()
    {

        /** @var \DataComparison\Config\DataComparison $config */
        $config = config('DataComparison');

        // Get default data sources from config
        $defaultDataSources = $config->defaultDataSources ?: '[]';

        /** @var string|null $json */
        $json = service('settings')
            ->get('DataComparison.dataSources', 'user:' . user_id());

        // Decode or start with default data source
        $dataSources = json_decode($json ?: $defaultDataSources, true);

        // Rewrite any relative URLs
        foreach ($dataSources as &$src) {
            if (isset($src['options']['url'])) {
                $url = $src['options']['url'];

                // If it doesn’t look like an absolute URL (scheme:// or // or www.)
                if (! preg_match('#^(?:[a-z][a-z0-9+\-.]*://|//|www\.)#i', $url)) {
                    // Prepend base_url()
                    $src['options']['url'] = base_url($url);
                }
            }
        }
        unset($src);

        // Pass into view as JSON string
        $this->data['dataSources'] = json_encode(
            $dataSources,
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        $this->data['models']     = $this->modelsManager->get();
        $this->data['title']      = lang('Dc.moduleName');

        return render('\DataComparison\Views\data_comparison', $this->data);
    }

    public function update()
    {
        $data = $this->request->getPost();

        $modelId = $data['modelId'];
        $entryId = $data['entryId'];
        $fields = $data['fields'];

        if (empty($entryId) || empty($fields)) {
            // Invalid request
            return $this->respond("Invalid request", statusCode: 400, success: false);
        }

        $entry = $this->entriesManager->find($entryId);

        if (empty($entry)) {
            // Not found
            return $this->respond(lang('Admin.noEntryFound'), statusCode: 404, success: false);
        }

        $mappedEntryFields = map_entry_fields($entry['fields']);

        foreach ($fields as $field) {
            $mappedEntryFields[$field['id']] = $field['value'];
        }

        $finalFields = unmap_entry_fields($mappedEntryFields);

        $this->entriesManager->update($entryId, [
            'fields' => $finalFields
        ], auth()->user()->id);

        return $this->respond(lang('Dc.entryUpdatedSuccessfully'));
    }
}
