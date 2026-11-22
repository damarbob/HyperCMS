<?php

namespace App\Data;

use App\Data\Entries\EntryEngine;
use CodeIgniter\Database\BaseConnection;

/**
 * The name of an entry, which is the value of the label field of its model. Lists and pickers show this instead of the
 * number of the entry.
 *
 * A page that shows relations asks for the labels it needs with `preload()`, so each label is not read on its own.
 */
class EntryLabels
{
    /**
     * Labels by entry id. A null label means the entry has none, and is not asked for again.
     *
     * @var array<int, string|null>
     */
    private array $labels = [];

    /**
     * The label field of each model by model id, read once.
     *
     * @var array<int, string|null>|null
     */
    private ?array $fields = null;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly EntryEngine $engine,
    ) {
    }

    /**
     * Read the labels of these entries.
     *
     * @param list<int|string> $entryIds
     */
    public function preload(array $entryIds): void
    {
        $wanted = array_values(array_filter(array_unique(array_map('intval', $entryIds)), fn (int $id): bool => $id > 0 && ! array_key_exists($id, $this->labels)));
        if ($wanted === []) {
            return;
        }

        $rows = $this->db->table('hyper_entries')->select('id, model_id, sd_entry_id')->whereIn('id', $wanted)->where('deleted_at', null)->get()->getResultArray();
        foreach ($wanted as $id) {
            $this->labels[$id] = null;
        }

        foreach ($rows as $row) {
            $field = $this->fields()[(int) $row['model_id']] ?? null;
            if ($field === null || $row['sd_entry_id'] === null) {
                continue;
            }
            $payload = $this->engine->get((int) $row['sd_entry_id']);
            $value   = $payload[$field] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $this->labels[(int) $row['id']] = (string) $value;
            }
        }
    }

    /**
     * The label of an entry, or null when it has none or no longer exists.
     */
    public function label(int|string $entryId): ?string
    {
        $id = (int) $entryId;
        $this->preload([$id]);

        return $this->labels[$id] ?? null;
    }

    /**
     * The live entries of a model to choose from, as label by entry id, in the order of their labels.
     *
     * An entry without a label is listed by its number. Only the first `$limit` entries are read, because a list of
     * thousands is not a list to choose from, and a search picker takes its place for large models.
     *
     * @return array<int, string>
     */
    public function options(int $modelId, int $limit = 300): array
    {
        $rows = $this->db->table('hyper_entries')->select('id')->where('model_id', $modelId)->where('deleted_at', null)->where('sd_entry_id IS NOT NULL', null, false)->orderBy('id')->limit($limit)->get()->getResultArray();
        $ids  = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $this->preload($ids);

        $options = [];
        foreach ($ids as $id) {
            $options[$id] = $this->labels[$id] ?? ('#' . $id);
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * @return array<int, string|null>
     */
    private function fields(): array
    {
        if ($this->fields === null) {
            $this->fields = [];
            foreach ($this->db->table('hyper_models')->select('id, label_field')->get()->getResultArray() as $row) {
                $this->fields[(int) $row['id']] = $row['label_field'] !== null && $row['label_field'] !== '' ? (string) $row['label_field'] : null;
            }
        }

        return $this->fields;
    }
}
