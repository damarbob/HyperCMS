<?php

namespace App\Data;

use App\Libraries\SortColumn;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use App\Data\Entries\EntryWriter;
use App\Data\Entries\FilterableFields;
use App\Libraries\Upgrade\ModelCopier;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use RuntimeException;
use StarDust\Exception\FieldDeletionInProgressException;
use StarDust\Exception\ModelDeletionInProgressException;
use StarDust\Exception\RenameInProgressException;
use StarDust\Exception\RetypeInProgressException;
use StarDust\StarDust;
use Throwable;

/**
 * The one place that reads and writes models.
 *
 * Controllers call this class and never touch StarDust themselves, so the engine behind it can
 * change without editing every controller. A model lives wholly in `hyper_models` and its saved
 * versions in `hyper_model_revisions`. StarDust 0.3 knows each model only as `m{id}` with the names
 * of its fields, so a model's labels, options and icons have no place there.
 */
class ModelStore
{
    use Transacts;

    public function __construct(
        private ?BaseConnection $db = null,
        private ?StarDust $engine = null,
        private ?WriteVersions $versions = null,
        private ?NamedLock $locks = null,
        private ?EntryWriter $entries = null,
    ) {
    }

    /**
     * Read one live model as a row with StarDust's own column names, or false when there is none.
     *
     * The edit form uses this row. `find()` returns the row with the legacy names the lists use.
     */
    public function findArray(int $id): array|false
    {
        return $this->listing()->where('m.id', $id)->get()->getResultArray()[0] ?? false;
    }

    /**
     * Create a model and return its id.
     */
    public function create(array $data, int $userId): int
    {
        $this->definition($data);
        $db  = $this->db();
        $now = $this->now();

        // The HyperCMS rows come first. StarDust cannot join this transaction, so the model exists in
        // HyperCMS before it is registered there, and a failed registration is finished by hyper:repair.
        $id = $this->transact(function () use ($db, $data, $userId, $now): int {
            $db->table('hyper_models')->insert([
                'name'          => (string) ($data['name'] ?? ''),
                'fields'        => $data['fields'],
                'group'         => $data['group'] ?? null,
                'icon'          => $data['icon'] ?? null,
                'user_groups'   => $data['user_groups'] ?? null,
                'label_field'   => $data['label_field'] ?? null,
                'creator_id'    => $userId,
                'editor_id'     => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
                'write_version' => 0,
            ]);
            $id = (int) $db->insertID();
            $db->table('hyper_model_revisions')->insert($this->revision($id, $data, $userId, $now));
            $db->table('hyper_models')->where('id', $id)->update(['current_revision_id' => (int) $db->insertID()]);

            return $id;
        });

        $this->grantAccess($id, $data);

        $this->locks()->run(NamedLock::forModel($id), function () use ($id): void {
            $this->syncAndBump($id);
        });

        return $id;
    }

    /**
     * Save a new version of a model.
     */
    public function update(int $id, array $data, int $userId): void
    {
        $this->definition($data);
        if ($this->db()->table('hyper_models')->where('id', $id)->countAllResults() === 0) {
            throw new LookupException("Model $id does not exist.");
        }

        $this->grantAccess($id, $data);

        $this->locks()->run(NamedLock::forModel($id), function () use ($id, $data, $userId): void {
            $db  = $this->db();
            $now = $this->now();

            $this->transact(function () use ($db, $id, $data, $userId, $now): void {
                $db->table('hyper_model_revisions')->insert($this->revision($id, $data, $userId, $now));
                $db->table('hyper_models')->where('id', $id)->update([
                    'name'                => (string) ($data['name'] ?? ''),
                    'fields'              => $data['fields'],
                    'group'               => $data['group'] ?? null,
                    'icon'                => $data['icon'] ?? null,
                    'user_groups'         => $data['user_groups'] ?? null,
                    'current_revision_id' => (int) $db->insertID(),
                    'editor_id'           => $userId,
                    'updated_at'          => $now,
                ] + (array_key_exists('label_field', $data) ? ['label_field' => $data['label_field']] : []));
            });

            // A field removed in the editor stays registered, so the values old entries hold for it survive.
            $this->syncAndBump($id);
        });
    }

    /**
     * Move models to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        foreach ($this->ids($ids) as $id) {
            $this->locks()->run(NamedLock::forModel($id), function () use ($id, $userId): void {
                // Trashing a model trashes the live entries it holds, and their payloads are kept for restore.
                $this->entries()->trashForModels([$id], $userId);

                $now = $this->now();
                $this->db()->table('hyper_models')->where('id', $id)->update(['deleter_id' => $userId, 'deleted_at' => $now, 'updated_at' => $now]);
                $this->versions()->bump([$id]);
            });
        }
    }

    /**
     * Read the trashed models among the given ids, or false when there are none.
     */
    public function trashed(array $ids): array|false
    {
        if ($ids === []) {
            return false;
        }

        return $this->listing(true)->whereIn('m.id', $ids)->get()->getResultArray() ?: false;
    }

    /**
     * Count the models in the trash.
     */
    public function countTrashed(): int|string
    {
        return $this->listing(true)->countAllResults();
    }

    /**
     * Delete the trashed models for good.
     */
    public function emptyTrash(): void
    {
        $db   = $this->db();
        $busy = 0;

        foreach ($db->table('hyper_models')->select('id, sd_model_id')->where('deleted_at IS NOT NULL', null, false)->get()->getResultArray() as $model) {
            $id = (int) $model['id'];

            $this->locks()->run(NamedLock::forModel($id), function () use ($db, $id, $model, &$busy): void {
                if ($model['sd_model_id'] !== null) {
                    // StarDust finishes the deletion later, from hyper:tick. A model it is already deleting is left to it.
                    try {
                        $this->engine()->deleteModel($this->tenant(), (int) $model['sd_model_id']);
                    } catch (ModelDeletionInProgressException) {
                    } catch (RetypeInProgressException | RenameInProgressException | FieldDeletionInProgressException) {
                        // A field is still being indexed, renamed or deleted, and StarDust refuses until that is done.
                        // The model stays in the trash and nothing of it is removed.
                        $busy++;

                        return;
                    }
                }

                // Every entry of the model goes with it, the trashed ones and any left live.
                $this->transact(function () use ($db, $id): void {
                    $db->query('DELETE r FROM hyper_entry_revisions r JOIN hyper_entries e ON e.id = r.entry_id WHERE e.model_id = ?', [$id]);
                    $db->table('hyper_entries')->where('model_id', $id)->delete();
                    $db->table('hyper_model_revisions')->where('model_id', $id)->delete();
                    $db->table('hyper_model_permissions')->where('model_id', $id)->delete();
                    $db->table('hyper_models')->where('id', $id)->delete();
                });

                $this->versions()->bumpGlobal();
            });
        }

        if ($busy > 0) {
            throw new RuntimeException(lang('Filterable.modelsBusy', ['count' => $busy]));
        }
    }

    /**
     * Bring models back from the trash.
     */
    public function restore(array $ids): void
    {
        foreach ($this->ids($ids) as $id) {
            $this->locks()->run(NamedLock::forModel($id), function () use ($id): void {
                $wasTrashed = $this->db()->table('hyper_models')->where('id', $id)->where('deleted_at IS NOT NULL', null, false)->countAllResults() > 0;

                $this->db()->table('hyper_models')->where('id', $id)->update(['deleted_at' => null, 'updated_at' => $this->now()]);
                if ($wasTrashed) {
                    // The entries come back with their model, the ones trashed earlier included.
                    $this->entries()->restoreForModels([$id]);
                }
                $this->versions()->bump([$id]);
            });
        }
    }

    /**
     * Delete every saved version of a model except the newest, for good.
     *
     * @return int|null The number of versions deleted, or null when the model has no versions.
     */
    public function clearHistory(int|string $modelId): ?int
    {
        $id     = (int) $modelId;
        $db     = $this->db();
        $newest = $db->table('hyper_model_revisions')->selectMax('id', 'newest')->where('model_id', $id)->get()->getRow()->newest ?? null;
        if ($newest === null) {
            return null;
        }

        $db->table('hyper_model_revisions')->where('model_id', $id)->where('id !=', $newest)->delete();

        return $db->affectedRows();
    }

    /**
     * Every saved version of one live model with what the version held, newest first.
     *
     * @return list<array<string, mixed>> The rows have `data_id` (the version), `name`, `fields`, `group`, `user_groups`,
     *         `icon`, `date_created` and `created_by`.
     */
    public function savedVersions(int $id): array
    {
        return $this->versionRows()->select(['r.group', 'r.user_groups'])->where('m.id', $id)->orderBy('r.id', 'DESC')->get()->getResultArray();
    }

    /**
     * Tell whether a live model with this id exists. A trashed model does not count.
     */
    public function exists(int|string $id): bool
    {
        return $this->db()->table('hyper_models')->where('id', $id)->where('deleted_at', null)->countAllResults() > 0;
    }

    /**
     * The ids of every model, live and trashed, because the entries of a trashed model can still show up in a trash view.
     *
     * @return list<int>
     */
    public function allIds(): array
    {
        return array_map('intval', array_column($this->db()->table('hyper_models')->select('id')->orderBy('id')->get()->getResultArray(), 'id'));
    }

    /**
     * Read the groups that may open each model, keyed by model id. Trashed models are included,
     * because their entries can still show up in a trash view.
     *
     * @return array<int, list<string>>
     */
    public function userGroups(): array
    {
        $map = [];
        foreach ([false, true] as $onlyTrashed) {
            foreach ($this->listing($onlyTrashed)->get()->getResultArray() as $row) {
                $groups = json_decode($row['user_groups'] ?? '[]', true);
                $map[(int) $row['id']] = is_array($groups) ? array_values($groups) : [];
            }
        }

        return $map;
    }

    /**
     * Compile the SQL that reads every live model as a flat table, without running it.
     *
     * The syntax processor wraps this SQL as a subquery so a form field can list models.
     */
    public function compiledSelect(): string
    {
        return $this->listing()->getCompiledSelect();
    }

    /**
     * Read every live model as rows with StarDust's own column names.
     */
    public function allArray(): array
    {
        return $this->listing()->get()->getResultArray();
    }

    /**
     * Read every live model as an array of rows.
     */
    public function all(): array
    {
        return $this->allArray();
    }

    /**
     * Read one live model, or null when there is none.
     */
    public function find(int|string $id): ?object
    {
        return $this->listing()->where('m.id', $id)->get()->getRow();
    }

    /**
     * Answer one DataTables request for the list of models.
     *
     * @param array $post The POST values DataTables sent (draw, start, length, search, order, columns, trash).
     * @param array $hiddenIds Models the caller may not read. They are left out of the rows and the counts.
     * @return array The DataTables response with draw, recordsTotal, recordsFiltered and data.
     */
    public function dataTable(array $post, array $hiddenIds = []): array
    {
        $draw = $post['draw'] ?? 1;
        $start = $post['start'] ?? null;
        $length = $post['length'] ?? -1;
        $search = $post['search']['value'] ?? '';
        $order = $post['order'] ?? null;
        $columns = $post['columns'] ?? null;
        $trash = $post['trash'] ?? false;

        // The trash view lists deleted models.
        $builder = $this->listing($trash && $trash != 'false');

        if (!empty($hiddenIds)) {
            $builder->whereNotIn('m.id', $hiddenIds);
        }

        $totalRecords = $builder->countAllResults(false);

        if (!empty($search)) {
            $builder->groupStart();
            $builder->like($this->column('name'), esc($search));
            $builder->orLike($this->column('fields'), esc($search));
            $builder->orLike($this->column('created_by'), esc($search));
            $builder->groupEnd();
        }

        $recordsFiltered = $builder->countAllResults(false);

        // DataTables sends the column index and direction, and the columns array names the
        // column. Only a plain column name may reach the query.
        $orderColumn = !empty($order) ? ($columns[$order[0]['column'] ?? 0]['data'] ?? null) : null;
        if (SortColumn::isSafe($orderColumn)) {
            $builder->orderBy($this->column($orderColumn), $order[0]['dir'] ?? '');
        } else {
            $builder->orderBy($this->column('date_modified'), 'DESC');
        }

        // A length of -1 means no limit.
        if ($length != -1) {
            $builder->limit(intval($length), intval($start));
        }

        return [
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $builder->get()->getResultArray(),
        ];
    }

    /**
     * Every live or every trashed model with the columns a list shows, under the names a list uses.
     *
     * `date_modified` is when the current version was saved, and `edited_by` is who saved it.
     */
    private function listing(bool $onlyTrashed = false): BaseBuilder
    {
        $users = $this->users();

        $builder = $this->db()->table('hyper_models AS m')
            ->select([
                'm.id',
                'm.created_at',
                'm.deleted_at AS date_deleted',
                'm.name',
                'm.fields',
                'm.label_field',
                'm.group',
                'm.user_groups',
                'm.icon',
                'r.created_at AS date_modified',
                'm.current_revision_id AS data_id',
                'c.' . $users['name'] . ' AS created_by',
                'e.' . $users['name'] . ' AS edited_by',
                'd.' . $users['name'] . ' AS deleted_by',
            ])
            ->join('hyper_model_revisions AS r', 'm.current_revision_id = r.id', 'left')
            ->join($users['table'] . ' AS c', 'm.creator_id = c.' . $users['id'], 'left')
            ->join($users['table'] . ' AS e', 'r.creator_id = e.' . $users['id'], 'left')
            ->join($users['table'] . ' AS d', 'm.deleter_id = d.' . $users['id'], 'left');

        return $onlyTrashed ? $builder->where('m.deleted_at IS NOT NULL', null, false) : $builder->where('m.deleted_at', null);
    }

    /**
     * Every saved version of a live model, with who saved it.
     */
    private function versionRows(): BaseBuilder
    {
        $users = $this->users();

        return $this->db()->table('hyper_model_revisions AS r')
            ->select([
                'r.id AS data_id',
                'r.name',
                'r.fields',
                'r.icon',
                'r.created_at AS date_created',
                'm.id',
                'c.' . $users['name'] . ' AS created_by',
            ])
            ->join('hyper_models AS m', 'r.model_id = m.id', 'left')
            ->join($users['table'] . ' AS c', 'r.creator_id = c.' . $users['id'], 'left')
            ->where('m.deleted_at', null);
    }

    /**
     * The table, key column and name column of the people who save models.
     *
     * @return array{table: string, id: string, name: string}
     */
    private function users(): array
    {
        $engine = config('HyperEngine');

        return ['table' => $engine->usersTable, 'id' => $engine->usersIdColumn, 'name' => $engine->usersUsernameColumn];
    }

    private function usersColumn(string $alias): string
    {
        return $alias . '.' . $this->users()['name'];
    }

    /**
     * Map a list column to the column it reads, as the legacy list names did. A name this does not know is
     * passed on as written, so an unknown column is a database error as before.
     */
    private function column(string $name): string
    {
        return match ($name) {
            'id'            => 'm.id',
            'name'          => 'm.name',
            'fields'        => 'm.fields',
            'group'         => 'm.group',
            'user_groups'   => 'm.user_groups',
            'icon'          => 'm.icon',
            'created_at'    => 'm.created_at',
            'date_modified' => 'r.created_at',
            'data_id'       => 'm.current_revision_id',
            'date_deleted'  => 'm.deleted_at',
            'created_by'    => $this->usersColumn('c'),
            'edited_by'     => $this->usersColumn('e'),
            'deleted_by'    => $this->usersColumn('d'),
            default         => $name,
        };
    }

    /**
     * Register the model in StarDust when it is not there yet, and return its StarDust id.
     *
     * Also finishes a create that stopped after the HyperCMS rows were written.
     *
     * @throws LookupException When there is no such model.
     */
    public function ensureRegistered(int $id): int
    {
        $row = $this->db()->table('hyper_models')->select('sd_model_id, fields')->where('id', $id)->get()->getRow();
        if ($row === null) {
            throw new LookupException("Model $id does not exist.");
        }
        if ($row->sd_model_id !== null) {
            return (int) $row->sd_model_id;
        }

        // StarDust creates a model by name, so naming it after the id keeps two models called "Article" apart.
        $definition = $this->engine()->schemaBuilder()->createModel($this->tenant(), 'm' . $id, ModelCopier::fieldDefinitions($row->fields));
        $this->db()->table('hyper_models')->where('id', $id)->update(['sd_model_id' => $definition->modelId]);

        return $definition->modelId;
    }

    /**
     * Register every field of the model's current definition in StarDust, and make each one filterable or not as
     * the definition says.
     *
     * A field is a string that is not filterable unless its definition says `"filterable": true`, so StarDust stores a
     * value exactly as written and never refuses one for its type. A flagged field is given the StarDust type its
     * field type maps to and then promoted, which StarDust backfills from `hyper:tick`. A field that is no longer
     * flagged, or was removed from the definition, is demoted. Demoting only frees the index. The values stay in
     * each entry, as they were written.
     *
     * @throws IndexingFailed When StarDust refuses to change a field, after every other field has been handled.
     */
    public function syncFields(int $id): void
    {
        $sdId = $this->ensureRegistered($id);
        $row  = $this->db()->table('hyper_models')->select('fields')->where('id', $id)->get()->getRow();
        $kind = FilterableFields::read($row->fields ?? null)['fields'];

        foreach (ModelCopier::fieldDefinitions($row->fields ?? null) as $field) {
            $this->engine()->schemaBuilder()->defineField($sdId, $field->name, $kind[$field->name] ?? 'string', false);
        }

        $failures  = [];
        $described = $this->engine()->describeModel($this->tenant(), $sdId);

        // StarDust's backfill reads only a date with seconds, so a field about to be indexed must hold clean values.
        foreach ($described?->fields ?? [] as $field) {
            if (isset($kind[$field->name]) && ! $field->isFilterable) {
                $this->cleanBeforeIndexing($id);

                break;
            }
        }

        foreach ($described?->fields ?? [] as $field) {
            $wanted = $kind[$field->name] ?? null;

            try {
                if ($wanted === null) {
                    if ($field->isFilterable) {
                        $this->engine()->demoteFieldFromFilterable($this->tenant(), $field->fieldId);
                    }

                    continue;
                }
                if ($field->declaredType !== $wanted) {
                    $this->engine()->retypeField($this->tenant(), $field->fieldId, $wanted);
                }
                if (! $field->isFilterable) {
                    $this->engine()->promoteFieldToFilterable($this->tenant(), $field->fieldId);
                }
            } catch (Throwable $e) {
                log_message('warning', 'Field {field} of model {model} could not be changed in StarDust. {error}', ['field' => $field->name, 'model' => $id, 'error' => $e->getMessage()]);
                $failures[$field->name] = $e->getMessage();
            }
        }

        if ($failures !== []) {
            throw new IndexingFailed($failures);
        }
    }

    /**
     * Clean the values of the model's filterable fields in its existing entries, before a field is promoted, so the
     * index starts with them. A model larger than `HyperEngine::normaliseLimit` is left to `hyper:normalize`, and an
     * entry holding a value that cannot be read stays as it is. Neither stops the model from being saved.
     */
    private function cleanBeforeIndexing(int $id): void
    {
        $live = $this->db()->table('hyper_entries')->where('model_id', $id)->where('deleted_at', null)->countAllResults();
        if ($live === 0) {
            return;
        }

        $limit = (int) config('HyperEngine')->normaliseLimit;
        if ($limit > 0 && $live > $limit) {
            log_message('warning', 'Model {model} has {entries} entries, so their values were not cleaned before indexing. Run hyper:normalize {model}.', ['model' => $id, 'entries' => $live]);

            return;
        }

        try {
            $report = $this->entries()->normaliseModel($id);
            foreach ($report['failed'] as $entryId => $reason) {
                log_message('warning', 'Entry {entry} of model {model} was left as it is. {reason}', ['entry' => $entryId, 'model' => $id, 'reason' => $reason]);
            }
        } catch (Throwable $e) {
            log_message('warning', 'Cleaning the entries of model {model} before indexing failed. {error}', ['model' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Where each filterable field of a model stands: `indexed` when lists can sort and filter on it now, and
     * `indexing` while StarDust is still backfilling it, which `hyper:tick` finishes.
     *
     * @return array<string, string> By field id, for the fields the definition marks filterable and no others.
     */
    public function filterableState(int $id): array
    {
        $row   = $this->db()->table('hyper_models')->select('fields, sd_model_id')->where('id', $id)->get()->getRow();
        $wants = FilterableFields::read($row->fields ?? null)['fields'];
        if ($wants === []) {
            return [];
        }

        $indexed = [];
        if ($row->sd_model_id !== null) {
            foreach ($this->engine()->describeModel($this->tenant(), (int) $row->sd_model_id)?->indexedFields() ?? [] as $field) {
                $indexed[$field->name] = true;
            }
        }

        $state = [];
        foreach ($wants as $name => $kind) {
            $state[$name] = isset($indexed[$name]) ? 'indexed' : 'indexing';
        }

        return $state;
    }

    /**
     * Give the roles of a model the actions the data names, when it names any.
     *
     * @param array<string, mixed> $data `access` holds the actions of each role.
     */
    private function grantAccess(int $id, array $data): void
    {
        if (isset($data['access']) && is_array($data['access'])) {
            service('grants')->setForModel($id, $data['access']);
        }
    }

    /**
     * Register the fields, then raise the write version, which a saved list is built under. A failure to start
     * indexing is reported after the version has risen, because the model itself was saved.
     */
    private function syncAndBump(int $id): void
    {
        $failed = null;

        try {
            $this->syncFields($id);
        } catch (IndexingFailed $e) {
            $failed = $e;
        }

        $this->versions()->bump([$id]);

        if ($failed !== null) {
            throw $failed;
        }
    }

    /**
     * The fields of a model definition, checked before anything is written.
     *
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException When the definition is missing or is not a JSON list.
     */
    private function definition(array $data): array
    {
        if (! isset($data['fields']) || ! is_string($data['fields'])) {
            throw new InvalidArgumentException('A model needs a `fields` definition.');
        }

        $fields = json_decode($data['fields'], true);
        if (! is_array($fields)) {
            throw new InvalidArgumentException('The `fields` of a model must be a JSON list.');
        }

        // A field that asks to be filterable must be of a type that can be.
        FilterableFields::assertValid($data['fields']);

        return $fields;
    }

    /**
     * The row of a saved version of a model.
     *
     * @return array<string, mixed>
     */
    private function revision(int $modelId, array $data, int $userId, string $now): array
    {
        return [
            'model_id'    => $modelId,
            'name'        => (string) ($data['name'] ?? ''),
            'fields'      => $data['fields'],
            'group'       => $data['group'] ?? null,
            'icon'        => $data['icon'] ?? null,
            'user_groups' => $data['user_groups'] ?? null,
            'creator_id'  => $userId,
            'created_at'  => $now,
        ];
    }

    /**
     * @return list<int>
     */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function now(): string
    {
        return Time::now()->toDateTimeString();
    }

    private function tenant(): int
    {
        return (int) config('HyperEngine')->tenantId;
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    private function engine(): StarDust
    {
        return $this->engine ??= service('stardust');
    }

    private function versions(): WriteVersions
    {
        return $this->versions ??= new WriteVersions($this->db());
    }

    private function locks(): NamedLock
    {
        return $this->locks ??= new NamedLock($this->db());
    }

    private function entries(): EntryWriter
    {
        return $this->entries ??= new EntryWriter();
    }
}
