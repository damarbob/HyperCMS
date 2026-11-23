<?php

namespace DataComparison\Controllers;

use App\Fields\FieldTypes;
use App\Modules\ModuleController;

/**
 * The comparison screen, and what it asks of the server: the entries of a model to put in a column, a value written back to
 * an entry, and the saved layouts ("states") of the person. A person reads only the models they may read, and writes to an
 * entry only when they may update entries of its model.
 */
class DataComparison extends ModuleController
{
    /** The kinds of field whose value is one line of text in a cell, so a cell can write it back. */
    private const WRITABLE = ['text', 'longtext', 'number', 'boolean', 'datetime', 'color'];

    /** The largest saved layouts a person can keep, in bytes of JSON. */
    private const MAX_STATES = 2_000_000;

    protected $helpers = ['hyper_url'];

    public function index()
    {
        /** @var \DataComparison\Config\DataComparison $config */
        $config  = config('DataComparison');
        $context = 'user:' . user_id();
        $settings = service('settings');

        // The sources of the person, or the ones the site starts with.
        $json    = $settings->get('DataComparison.dataSources', $context);
        $sources = json_decode(is_string($json) && $json !== '' ? $json : ($config->defaultDataSources ?: '[]'), true);
        $sources = is_array($sources) ? array_values(array_filter($sources, 'is_array')) : [];
        foreach ($sources as &$source) {
            $url = $source['options']['url'] ?? null;
            // An address that does not name a place of its own is one of this site.
            if (is_string($url) && preg_match('#^(?:[a-z][a-z0-9+\-.]*://|//|www\.)#i', $url) !== 1) {
                $source['options']['url'] = base_url($url);
            }
        }
        unset($source);

        $states = json_decode((string) $settings->get('DataComparison.states', $context), true);

        $labels = [];
        foreach (['entryx', 'chooseEntry', 'selectModel', 'chooseModel', 'chooseField', 'failedToFetchEntries', 'failedToFetchEntryData', 'entryFromExternalDataSource', 'entryUpdatedSuccessfully', 'statexSavedSuccessfully', 'statexDeletedSuccessfully', 'failedToSaveStates', 'statexAlreadyExistsOverwrite', 'youHaveUnsavedChangesLoadAnyway', 'deleteStatex', 'pleaseProvideBothFieldLabelAndId', 'pleaseProvideFormula', 'pleaseSelectField', 'pleaseSelectFieldType', 'pleaseAddEntryFirst', 'pleaseFillAllRequiredFields', 'columnWithSameIdAlreadyExists', 'fieldxAlreadyExists', 'formulaError'] as $key) {
            $labels[$key] = lang('Dc.' . $key);
        }

        return $this->page('DataComparison\Views\data_comparison', [
            'title' => lang('Dc.moduleName'),
            'comparison' => [
                'models'  => $this->readableModels(),
                'sources' => $sources,
                'states'  => is_array($states) && ! array_is_list($states) ? $states : new \stdClass(),
                'last'    => (string) $settings->get('DataComparison.lastUsedState', $context),
                'urls'    => [
                    'entries' => base_url('admin/data-comparison/entries'),
                    'update'  => base_url('admin/data-comparison/update'),
                    'states'  => base_url('admin/data-comparison/states'),
                    'last'    => base_url('admin/data-comparison/last'),
                ],
                'csrf'    => ['name' => csrf_token(), 'hash' => csrf_hash()],
                'labels'  => $labels,
            ],
        ]);
    }

    /**
     * The entries of one model, newest first, for the list that puts an entry in a column. An entry is named by the label
     * field of its model, or by its number.
     */
    public function entries()
    {
        $modelId = (int) $this->request->getPost('model_id');
        $model   = $modelId > 0 ? service('modelStore')->findArray($modelId) : false;
        if (! $model || ! service('modelAccess')->canRead(auth()->user(), $modelId)) {
            return $this->json(['message' => lang('Auth.notEnoughPrivilege')], 403);
        }

        $label = (string) ($model['label_field'] ?? '');
        $rows  = array_slice(service('entryStore')->recent(['model_id' => $modelId, 'hidden_model_ids' => []]), 0, 500);
        $list  = [];
        foreach ($rows as $row) {
            $fields = json_decode((string) ($row['fields'] ?? ''), true);
            $text   = $label !== '' && is_array($fields) && isset($fields[$label]) && is_scalar($fields[$label]) ? trim((string) $fields[$label]) : '';
            $list[] = ['id' => (int) $row['id'], 'label' => $text !== '' ? mb_strimwidth($text, 0, 80, '...') : lang('Dc.entryx', ['x' => $row['id']])];
        }

        return $this->json(['data' => $list]);
    }

    /**
     * Write one value into a field of an entry, the way the entry form would: read by the type of the field, checked, and
     * saved as a new version of the entry.
     */
    public function update()
    {
        $entryId = (int) $this->request->getPost('entry_id');
        $fieldId = (string) $this->request->getPost('field_id');
        $value   = $this->request->getPost('value');

        $entry = $entryId > 0 ? service('entryStore')->findArray($entryId) : false;
        if (! $entry || $fieldId === '' || ! is_string($value)) {
            return $this->json(['message' => lang('Admin.noEntryFound')], 404);
        }

        $modelId = (int) $entry['model_id'];
        if (! $this->allowed('entries.update', $modelId)) {
            return $this->json(['message' => lang('Auth.notEnoughPrivilege')], 403);
        }

        $model = service('modelStore')->findArray($modelId);
        $field = null;
        foreach (json_decode((string) ($model['fields'] ?? '[]'), true) ?: [] as $definition) {
            if (is_array($definition) && ($definition['id'] ?? null) === $fieldId) {
                $field = $definition;
            }
        }
        $kind = (string) ($field['type'] ?? '');
        $type = $field === null ? null : FieldTypes::core()->get($kind);
        if ($type === null || ! in_array($kind, self::WRITABLE, true) || ($kind === 'text' && ($field['format'] ?? '') === 'password')) {
            return $this->json(['message' => lang('Dc.cellCannotBeWritten')], 422);
        }

        $clean = $type->normalise($value, $field);
        $error = $type->validate($clean, $field);
        if ($error !== null) {
            return $this->json(['message' => $error], 422);
        }

        $values = json_decode((string) $entry['fields'], true);
        $values = is_array($values) && ! array_is_list($values) ? $values : [];
        $values[$fieldId] = $clean;
        service('entryStore')->update($entryId, ['fields' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], (int) auth()->id());

        return $this->json(['message' => lang('Dc.entryUpdatedSuccessfully'), 'value' => is_scalar($clean) ? (string) $clean : '']);
    }

    /**
     * Keep the saved layouts of the person, which the script holds as one object of name to layout.
     */
    public function saveStates()
    {
        $text   = $this->request->getPost('states');
        $states = is_string($text) && strlen($text) <= self::MAX_STATES ? json_decode($text, true) : null;
        if (! is_array($states) || ($states !== [] && array_is_list($states))) {
            return $this->json(['message' => lang('Dc.invalidJsonFormatForStates')], 422);
        }

        service('settings')->set('DataComparison.states', json_encode($states, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'user:' . user_id());

        return $this->json(['message' => lang('Admin.settingsSuccessfullySaved')]);
    }

    /**
     * Remember which layout the person had open, so the screen opens on it next time.
     */
    public function saveLast()
    {
        $name = (string) $this->request->getPost('name');
        service('settings')->set('DataComparison.lastUsedState', mb_substr($name, 0, 200), 'user:' . user_id());

        return $this->json(['ok' => true]);
    }

    /**
     * The models the person may read, with the fields each one has, for the pickers of the screen.
     *
     * @return list<array<string, mixed>>
     */
    private function readableModels(): array
    {
        $access = service('modelAccess');
        $user   = auth()->user();
        $models = [];
        foreach (service('modelStore')->allArray() as $model) {
            if (! $access->canRead($user, (int) $model['id'])) {
                continue;
            }
            $fields = [];
            foreach (json_decode((string) ($model['fields'] ?? '[]'), true) ?: [] as $definition) {
                if (is_array($definition) && is_string($definition['id'] ?? null)) {
                    $fields[] = ['id' => $definition['id'], 'label' => (string) ($definition['label'] ?? $definition['id']), 'type' => (string) ($definition['type'] ?? 'text')];
                }
            }
            $models[] = ['id' => (int) $model['id'], 'name' => (string) $model['name'], 'fields' => $fields];
        }

        return $models;
    }
}
