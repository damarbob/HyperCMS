<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Data\IndexingFailed;
use App\Fields\DefinitionCheck;
use App\Fields\FieldTypes;
use InvalidArgumentException;
use RuntimeException;

/**
 * The saved versions of one model, drawn on the server. Each save of a model is a version, and an older one can be
 * saved again as a new version.
 */
class ModelData extends AdminController
{
    public function show($modelId)
    {
        $model = service('modelStore')->findArray((int) $modelId);
        if (! $model) {
            return redirect()->to(base_url('admin/models'))->with('error', lang('Models.notFound'));
        }

        $rows     = service('modelStore')->savedVersions((int) $modelId);
        $versions = [];
        foreach ($rows as $index => $row) {
            $fields     = self::fieldsOf((string) $row['fields']);
            $older      = isset($rows[$index + 1]) ? self::fieldsOf((string) $rows[$index + 1]['fields']) : null;
            $versions[] = [
                'id'      => (int) $row['data_id'],
                'number'  => count($rows) - $index,
                'current' => (int) $row['data_id'] === (int) $model['data_id'],
                'date'    => $row['date_created'],
                'by'      => $row['created_by'],
                'name'    => (string) $row['name'],
                'group'   => (string) ($row['group'] ?? ''),
                'fields'  => $fields,
                'changes' => $older === null ? [] : self::changes($older, $fields),
            ];
        }

        $this->data = array_merge($this->data, [
            'title'    => lang('Models.historyTitle', ['name' => $model['name']]),
            'crumbs'   => [[lang('Models.models'), base_url('admin/models')], [$model['name'], base_url('admin/models/' . (int) $model['id'] . '/edit')], [lang('Models.history'), null]],
            'model'    => $model,
            'versions' => $versions,
            'urls'     => ['edit' => base_url('admin/models/' . (int) $model['id'] . '/edit'), 'base' => base_url('admin/model-data/' . (int) $model['id'])],
        ]);

        return view('admin/model_data', $this->data);
    }

    /**
     * Save an older version as a new one, so the model goes back to what it was and the older history stays.
     */
    public function restoreVersion($modelId, $versionId)
    {
        $back = base_url('admin/model-data/' . (int) $modelId);

        if (! service('modelStore')->exists((int) $modelId)) {
            return $this->leave(base_url('admin/models'), lang('Models.notFound'));
        }

        $found = null;
        foreach (service('modelStore')->savedVersions((int) $modelId) as $row) {
            if ((int) $row['data_id'] === (int) $versionId) {
                $found = $row;
                break;
            }
        }
        if ($found === null) {
            return $this->leave($back, lang('Models.versionNotFound'));
        }

        // An old version is saved again under today's rules, and the field that names the entries must still be in it.
        $names = [];
        foreach (service('modelStore')->allArray() as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }
        $check  = new DefinitionCheck(FieldTypes::core(), $names);
        $result = $check->check(json_decode((string) $found['fields'], true));
        if ($result['errors'] !== []) {
            return $this->leave($back, lang('Models.versionInvalid', ['message' => $result['errors'][0]['message']]));
        }

        $model = service('modelStore')->findArray((int) $modelId);
        $label = is_array($model) && is_string($model['label_field'] ?? null) ? $model['label_field'] : null;

        try {
            service('modelStore')->update((int) $modelId, [
                'name'        => $found['name'],
                'fields'      => $found['fields'],
                'group'       => $found['group'],
                'icon'        => $found['icon'],
                'user_groups' => is_array($model) ? $model['user_groups'] : $found['user_groups'],
                'label_field' => $check->checkLabelField($label, $result['fields']) === null ? $label : null,
            ], (int) auth()->user()->id);
        } catch (IndexingFailed $e) {
            // The version is saved again. Only the start of the indexing failed.
            session()->setFlashdata('warning', $e->getMessage());

            return $this->go($back);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->leave($back, $e->getMessage());
        }

        session()->setFlashdata('success', lang('Models.versionRestored'));

        return $this->go($back);
    }

    public function clearHistory($modelId = null)
    {
        $back = base_url('admin/model-data/' . (int) $modelId);

        $affected = $modelId === null || $modelId === '' ? null : service('modelStore')->clearHistory($modelId);
        if ($affected === null) {
            return $this->leave($back, lang('Models.noHistory'));
        }
        if ($affected === 0) {
            return $this->leave($back, lang('Models.noHistoryCleared'));
        }

        session()->setFlashdata('success', lang('Models.historyCleared'));

        return $this->go($back);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fieldsOf(string $json): array
    {
        $fields = json_decode($json, true);

        return is_array($fields) ? array_values(array_filter($fields, static fn ($f): bool => is_array($f) && is_string($f['id'] ?? null))) : [];
    }

    /**
     * What a version changed compared with the one before it, by field id.
     *
     * @param list<array<string, mixed>> $before
     * @param list<array<string, mixed>> $after
     *
     * @return list<array{kind: string, field: string}> `added`, `removed` or `changed` for each field that differs.
     */
    private static function changes(array $before, array $after): array
    {
        $old = array_column($before, null, 'id');
        $new = array_column($after, null, 'id');

        $changes = [];
        foreach ($new as $id => $field) {
            if (! isset($old[$id])) {
                $changes[] = ['kind' => 'added', 'field' => (string) $id];
            } elseif ($old[$id] != $field) {
                $changes[] = ['kind' => 'changed', 'field' => (string) $id];
            }
        }
        foreach ($old as $id => $field) {
            if (! isset($new[$id])) {
                $changes[] = ['kind' => 'removed', 'field' => (string) $id];
            }
        }

        return $changes;
    }

    private function leave(string $to, string $message)
    {
        session()->setFlashdata('error', $message);

        return $this->go($to);
    }

    private function go(string $to)
    {
        return $this->request->hasHeader('HX-Request') ? $this->response->setHeader('HX-Redirect', $to)->setStatusCode(204) : redirect()->to($to);
    }
}
