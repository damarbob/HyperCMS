<?php

namespace App\Data\Entries;

use CodeIgniter\Database\BaseConnection;

/**
 * Single and module reads of entries: one entry, several entries for modules, the trash, counts
 * and the history of one entry. A row is a hyper_entries row plus its fields from StarDust,
 * assembled by EntryRows.
 *
 * A live entry whose create or restore is still in progress has no StarDust id yet, and the reads skip it.
 */
class EntryReader
{
    /**
     * The most bytes a stored payload may have for blankFor() to look at the entry. A blank payload is
     * `[]` in StarDust, so this leaves a wide margin and spares a scan of every body.
     */
    private const BLANK_PAYLOAD_BYTES = 64;

    public function __construct(
        private ?BaseConnection $db = null,
        private ?EntryRows $rows = null,
        private ?EntryPayloads $payloads = null,
    ) {
    }

    /**
     * Read one live entry as a row with StarDust's own column names, or false when there is none.
     */
    public function findArray(int $id): array|false
    {
        return $this->one($id) ?? false;
    }

    /**
     * Read one live entry with the legacy column names, or null when there is none.
     */
    public function findAliased(int|string $id): ?array
    {
        return $this->one($id);
    }

    /**
     * Read one entry by its id, or null when there is none.
     */
    public function findRow($id): ?array
    {
        return $this->one($id);
    }

    /**
     * Read the id of the model an entry belongs to, or null when the entry has no saved version.
     * A trashed entry has none, as before, because its versions went to the trash with it.
     */
    public function modelIdOf(int|string $entryId): ?int
    {
        $row = $this->db()->table('hyper_entries')
            ->select('model_id')
            ->where('id', $entryId)
            ->where('deleted_at', null)
            ->where('sd_entry_id IS NOT NULL', null, false)
            ->get()
            ->getRow();

        return $row ? (int) $row->model_id : null;
    }

    /**
     * Read every live entry as rows with StarDust's own column names.
     */
    public function allArray(): array
    {
        return $this->rows()->hydrate($this->rows()->query()->orderBy('e.id')->get()->getResultArray());
    }

    /**
     * Read the live entries of the given models, with the legacy column names.
     */
    public function ofModels(array $modelIds): array
    {
        if ($modelIds === []) {
            return [];
        }

        return $this->rows()->hydrate($this->rows()->query()->whereIn('e.model_id', $modelIds)->orderBy('e.id')->get()->getResultArray());
    }

    /**
     * Read the live entries of the model with this name, with the legacy column names.
     */
    public function named(string $modelName): array
    {
        return $this->rows()->hydrate($this->rows()->query()->where('m.name', $modelName)->orderBy('e.id')->get()->getResultArray());
    }

    /**
     * Read live entries newest first, with the legacy column names.
     *
     * @param array $filters Optional keys `id`, `ids`, `model_id` and `hidden_model_ids`. An empty filter is skipped.
     */
    public function recent(array $filters = []): array
    {
        $builder = $this->rows()->query();

        if (! empty($filters['hidden_model_ids'])) {
            $builder->whereNotIn('e.model_id', $filters['hidden_model_ids']);
        }

        if (! empty($filters['id'])) {
            $builder->where('e.id', $filters['id']);
        }

        if (! empty($filters['ids'])) {
            $builder->whereIn('e.id', $filters['ids']);
        }

        if (! empty($filters['model_id'])) {
            $builder->where('e.model_id', $filters['model_id']);
        }

        return $this->rows()->hydrate($builder->orderBy('e.updated_at', 'DESC')->orderBy('e.id', 'ASC')->get()->getResultArray());
    }

    /**
     * Read the newest live entry of a model that has no field values, or null when there is none.
     *
     * StarDust stores a blank payload as `[]`. Only entries with a small stored payload are read, newest first.
     */
    public function blankFor(int|string $modelId): ?array
    {
        $candidates = $this->rows()->query()
            ->where('e.model_id', $modelId)
            ->where('e.payload_bytes <=', self::BLANK_PAYLOAD_BYTES)
            ->orderBy('e.updated_at', 'DESC')
            ->orderBy('e.id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($this->payloads()->chunks($candidates) as $chunk) {
            $fields = $this->payloads()->read($chunk);
            foreach ($chunk as $row) {
                if (isset($fields[(int) $row['id']]) && $fields[(int) $row['id']] === []) {
                    return $this->rows()->assemble($row, []);
                }
            }
        }

        return null;
    }

    /**
     * Read the trashed entries among the given ids, or false when there are none.
     */
    public function trashed(array $ids): array|false
    {
        if ($ids === []) {
            return false;
        }

        return $this->rows()->hydrate($this->rows()->query(true)->whereIn('e.id', $ids)->orderBy('e.id')->get()->getResultArray(), true) ?: false;
    }

    /**
     * Count the entries in the trash.
     */
    public function countTrashed(): int|string
    {
        return $this->db()->table('hyper_entries')->where('deleted_at IS NOT NULL', null, false)->countAllResults();
    }

    /**
     * Count the live entries of one model.
     */
    public function countForModel(int|string $modelId): int
    {
        return $this->db()->table('hyper_entries')
            ->where('model_id', $modelId)
            ->where('deleted_at', null)
            ->where('sd_entry_id IS NOT NULL', null, false)
            ->countAllResults();
    }

    /**
     * Read the saved versions of one entry as rows. The versions of a trashed entry are not listed, as before.
     */
    public function versionsOf(int|string $entryId): array
    {
        return $this->revisions($entryId);
    }

    /**
     * Every saved version of one live entry, oldest first, as `entry_id`, `id`, `fields`, `date_created`, `model_id`,
     * `model_name` and `created_by`. The version that is live has no stored fields and is read from StarDust.
     *
     * @return list<array<string, mixed>>
     */
    public function revisions(int|string $entryId): array
    {
        $users = $this->rows()->users();

        $rows = $this->db()->table('hyper_entry_revisions AS r')
            ->select([
                'r.entry_id',
                'r.id',
                'r.fields',
                'r.created_at AS date_created',
                'e.model_id',
                'm.name AS model_name',
                'c.' . $users['name'] . ' AS created_by',
                'e.current_revision_id',
                'e.sd_entry_id',
            ])
            ->join('hyper_entries AS e', 'r.entry_id = e.id', 'left')
            ->join('hyper_models AS m', 'e.model_id = m.id', 'left')
            ->join($users['table'] . ' AS c', 'r.creator_id = c.' . $users['id'], 'left')
            ->where('r.entry_id', $entryId)
            ->where('e.deleted_at', null)
            ->where('e.sd_entry_id IS NOT NULL', null, false)
            ->orderBy('r.id')
            ->get()
            ->getResultArray();

        // The live version of a live entry holds no text of its own, because its fields are in StarDust.
        $live = [];
        foreach ($rows as $row) {
            if ($row['fields'] === null && (int) $row['id'] === (int) $row['current_revision_id']) {
                $live[] = ['id' => $row['entry_id'], 'sd_entry_id' => $row['sd_entry_id'], 'payload_bytes' => 0];
            }
        }
        $current = $live === [] ? [] : $this->payloads()->read($live);

        $versions = [];
        foreach ($rows as $row) {
            $fields = $row['fields'];
            if ($fields === null && isset($current[(int) $row['entry_id']]) && (int) $row['id'] === (int) $row['current_revision_id']) {
                $fields = FieldRules::fieldsText($current[(int) $row['entry_id']]);
            }

            $versions[] = [
                'entry_id'     => $row['entry_id'],
                'id'           => $row['id'],
                'fields'       => $fields,
                'date_created' => $row['date_created'],
                'model_id'     => $row['model_id'],
                'model_name'   => $row['model_name'],
                'created_by'   => $row['created_by'],
            ];
        }

        return $versions;
    }

    /**
     * Compile the SQL that reads every live entry as a flat table, without running it.
     *
     * The columns are the ones a row has, apart from `fields`, which is no longer in the database. Asking for it
     * is an unknown column, and the syntax processor answers a query on the fields in PHP.
     */
    public function compiledSelect(): string
    {
        return $this->rows()->query()->getCompiledSelect();
    }

    private function one(int|string $id): ?array
    {
        $rows = $this->rows()->hydrate($this->rows()->query()->where('e.id', $id)->limit(1)->get()->getResultArray());

        return $rows[0] ?? null;
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    private function rows(): EntryRows
    {
        return $this->rows ??= new EntryRows($this->db(), $this->payloads());
    }

    private function payloads(): EntryPayloads
    {
        return $this->payloads ??= new EntryPayloads();
    }
}
