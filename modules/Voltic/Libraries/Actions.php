<?php

namespace Voltic\Libraries;

use App\Fields\DefinitionCheck;
use App\Fields\FieldTypes;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

/**
 * What the AI may propose, and what happens when the person confirms it.
 *
 * An action is read into a plain shape twice, when it is proposed and again when it is confirmed, so what runs is always what
 * this class understood and never what a browser sent. Each action runs under the abilities of the person, the same ones the
 * screens of the admin check.
 */
class Actions
{
    /** The types of field the AI may put in a model it makes. A file, a relation and the like need a person to choose. */
    public const FIELD_TYPES = ['text', 'longtext', 'richtext', 'number', 'boolean', 'choice', 'datetime', 'color'];

    /** The types of field that an entry made by the AI leaves empty. */
    private const LEFT_EMPTY = ['file', 'relation', 'model', 'hook', 'hidden'];

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(private readonly User $user)
    {
    }

    /**
     * Read an action as the AI wrote it, or null when it is not one this class knows.
     *
     * @return array{type: string, params: array<string, mixed>}|null
     */
    public function normalise(mixed $action): ?array
    {
        if (! is_array($action) || ! is_string($action['type'] ?? null)) {
            return null;
        }

        $params = $this->params($action['params'] ?? []);
        switch (str_replace('-', '_', strtolower($action['type']))) {
            case 'create_model':
                $name   = is_string($params['name'] ?? null) ? trim($params['name']) : '';
                $fields = $params['fields'] ?? null;

                return $name !== '' && is_array($fields) && array_is_list($fields) ? ['type' => 'create_model', 'params' => ['name' => mb_substr($name, 0, 255), 'fields' => $fields]] : null;

            case 'create_entry':
                $values = $params['values'] ?? $params['fields'] ?? null;
                // The list of {id, value} that an earlier prompt asked for is read as well.
                if (is_array($values) && array_is_list($values) && $values !== [] && isset($values[0]['id'])) {
                    $values = array_column($values, 'value', 'id');
                }
                $modelId = (int) ($params['model_id'] ?? 0);

                return $modelId > 0 && is_array($values) && ! array_is_list($values) ? ['type' => 'create_entry', 'params' => ['model_id' => $modelId, 'values' => $values]] : null;

            case 'show_entry':
                $id = (int) ($params['id'] ?? 0);

                return $id > 0 ? ['type' => 'show_entry', 'params' => ['id' => $id]] : null;
        }

        return null;
    }

    /**
     * A line that says what the action would do, for the button the person presses.
     *
     * @param array{type: string, params: array<string, mixed>} $action
     */
    public function describe(array $action): string
    {
        $p = $action['params'];
        switch ($action['type']) {
            case 'create_model':
                return lang('Voltic.actionCreateModel', ['name' => $p['name'], 'count' => count($p['fields'])]);
            case 'create_entry':
                $model = service('modelStore')->findArray((int) $p['model_id']);

                return lang('Voltic.actionCreateEntry', ['model' => $model ? $model['name'] : '#' . $p['model_id']]);
            default:
                return lang('Voltic.actionShowEntry', ['id' => $p['id']]);
        }
    }

    /**
     * Why the person may not run the action, or null when they may.
     *
     * @param array{type: string, params: array<string, mixed>} $action
     */
    public function refusal(array $action): ?string
    {
        $p = $action['params'];
        switch ($action['type']) {
            case 'create_model':
                return can('models.manage') ? null : lang('Voltic.userDoesNotHaveSufficientPermission');
            case 'create_entry':
                $modelId = (int) $p['model_id'];

                return service('modelAccess')->canRead($this->user, $modelId) && can('entries.create', $modelId) ? null : lang('Voltic.userDoesNotHaveSufficientPermission');
            default:
                $modelId = (int) (service('entryStore')->modelIdOf((int) $p['id']) ?? 0);

                return $modelId > 0 && service('modelAccess')->canRead($this->user, $modelId) && can('entries.read', $modelId) ? null : lang('Voltic.userDoesNotHaveSufficientPermission');
        }
    }

    /**
     * Do the action.
     *
     * @param array{type: string, params: array<string, mixed>} $action
     *
     * @return array{message: string, links: list<array{text: string, href: string}>, rows: list<array{label: string, value: string}>}
     *
     * @throws RuntimeException With a message the person can read.
     */
    public function run(array $action): array
    {
        $refusal = $this->refusal($action);
        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }

        return match ($action['type']) {
            'create_model' => $this->createModel($action['params']),
            'create_entry' => $this->createEntry($action['params']),
            default        => $this->showEntry((int) $action['params']['id']),
        };
    }

    /**
     * @param array<string, mixed> $p
     */
    private function createModel(array $p): array
    {
        $fields = [];
        foreach ($p['fields'] as $field) {
            if (! is_array($field) || ! in_array($field['type'] ?? '', self::FIELD_TYPES, true)) {
                throw new RuntimeException(lang('Voltic.fieldTypeNotAllowed', ['types' => implode(', ', self::FIELD_TYPES)]));
            }
            $fields[] = $this->choicesAsList($field);
        }

        $names = [];
        foreach (service('modelStore')->allArray() as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }
        $result = (new DefinitionCheck(FieldTypes::core(), $names))->check($fields);
        if ($result['errors'] !== []) {
            throw new RuntimeException(implode(' ', array_slice(array_map(static fn (array $e): string => (string) $e['message'], $result['errors']), 0, 3)));
        }

        // The first line of text names an entry in lists, as the builder would suggest.
        $label = null;
        foreach ($result['fields'] as $field) {
            if (($field['type'] ?? '') === 'text') {
                $label = (string) $field['id'];
                break;
            }
        }
        $id = service('modelStore')->create(['name' => $p['name'], 'fields' => json_encode($result['fields'], self::JSON_FLAGS), 'label_field' => $label], (int) $this->user->id);

        return [
            'message' => lang('Voltic.modelxCreatedSuccessfully', ['x' => $p['name']]),
            'links'   => [
                ['text' => lang('Admin.editx', ['x' => lang('Admin.model')]), 'href' => base_url("admin/models/$id/edit")],
                ['text' => lang('Admin.newx', ['x' => lang('Admin.entry')]), 'href' => base_url("admin/model/$id/new")],
            ],
            'rows' => [],
        ];
    }

    /**
     * The choices of a field as the list of `{value, label}` the builder keeps. An AI may write them as a map of value to label,
     * or as a list of plain values, and both are read.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function choicesAsList(array $field): array
    {
        $choices = $field['choices'] ?? null;
        if (! is_array($choices)) {
            return $field;
        }

        $list = [];
        foreach ($choices as $key => $item) {
            if (is_array($item)) {
                $list[] = $item;
            } elseif (array_is_list($choices)) {
                $list[] = ['value' => $item, 'label' => $item];
            } else {
                $list[] = ['value' => (string) $key, 'label' => $item];
            }
        }
        $field['choices'] = $list;

        return $field;
    }

    /**
     * @param array<string, mixed> $p
     */
    private function createEntry(array $p): array
    {
        $modelId = (int) $p['model_id'];
        $model   = service('modelStore')->findArray($modelId);
        if (! $model) {
            throw new RuntimeException(lang('Admin.noEntryFound'));
        }

        // Each value is read by the type of its field and checked, as the form of the entry would.
        $types  = FieldTypes::core();
        $values = [];
        $errors = [];
        foreach (json_decode((string) $model['fields'], true) ?: [] as $field) {
            $type = is_array($field) ? $types->get((string) ($field['type'] ?? '')) : null;
            if ($type === null || in_array($field['type'], self::LEFT_EMPTY, true)) {
                continue;
            }
            $value = $type->normalise($p['values'][$field['id']] ?? null, $field);
            $error = $type->validate($value, $field);
            if ($error !== null) {
                $errors[] = ($field['label'] ?? $field['id']) . ': ' . $error;
            }
            $values[$field['id']] = $value;
        }
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', array_slice($errors, 0, 4)));
        }

        $id = service('entryStore')->create(['model_id' => $modelId, 'fields' => json_encode($values, self::JSON_FLAGS)], (int) $this->user->id);

        return [
            'message' => lang('Voltic.entryxCreatedSuccessfully', ['x' => $model['name']]),
            'links'   => [['text' => lang('Admin.editx', ['x' => lang('Admin.entry')]), 'href' => base_url("admin/model/$modelId/$id/edit")]],
            'rows'    => [],
        ];
    }

    private function showEntry(int $id): array
    {
        $entry = service('entryStore')->findArray($id);
        if (! $entry) {
            throw new RuntimeException(lang('Admin.noEntryFound'));
        }
        $modelId = (int) $entry['model_id'];
        $model   = service('modelStore')->findArray($modelId);
        $values  = json_decode((string) $entry['fields'], true);
        $values  = is_array($values) && ! array_is_list($values) ? $values : [];

        $rows = [];
        foreach (json_decode((string) ($model['fields'] ?? '[]'), true) ?: [] as $field) {
            if (! is_array($field) || ! isset($field['id'])) {
                continue;
            }
            // A password is never shown, and a long text is cut so the chat stays readable.
            if (($field['type'] ?? '') === 'text' && ($field['format'] ?? '') === 'password') {
                continue;
            }
            $value  = $values[$field['id']] ?? null;
            $text   = is_scalar($value) ? (string) $value : json_encode($value, self::JSON_FLAGS);
            $rows[] = ['label' => (string) ($field['label'] ?? $field['id']), 'value' => mb_strimwidth((string) $text, 0, 1500, '...')];
        }

        return [
            'message' => lang('Voltic.showingEntryx', ['x' => $model['name'] ?? '']),
            'links'   => [['text' => lang('Admin.editx', ['x' => lang('Admin.entry')]), 'href' => base_url("admin/model/$modelId/$id/edit")]],
            'rows'    => $rows,
        ];
    }

    /**
     * The parameters as a map. The AI may write them as a map, or as the list of `{id, value}` that an earlier prompt asked for.
     *
     * @return array<string, mixed>
     */
    private function params(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        if (array_is_list($raw) && $raw !== [] && is_array($raw[0]) && isset($raw[0]['id'])) {
            $map = [];
            foreach ($raw as $item) {
                if (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                    $map[$item['id'] === 'field' ? 'fields' : $item['id']] = $item['value'] ?? null;
                }
            }

            return $map;
        }

        return $raw;
    }
}
