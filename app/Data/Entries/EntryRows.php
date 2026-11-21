<?php

namespace App\Data\Entries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\HyperEngine;

/**
 * Builds the legacy row an entry has always been returned as, from a hyper_entries row, its model,
 * the usernames and the entry's fields in StarDust 0.3.
 *
 * It also owns the SQL every read starts from, so that the live list, the trash view and the point
 * reads select the same columns under the same names.
 */
class EntryRows
{
    public function __construct(private ?BaseConnection $db = null, private ?EntryPayloads $payloads = null)
    {
    }

    /**
     * Turn rows of `query()` into legacy rows, reading the fields of live entries from StarDust a chunk at a time.
     *
     * A row whose entry StarDust does not return is logged by EntryPayloads and left out. Trashed rows keep their
     * snapshot text. The order of the rows is kept.
     *
     * @param list<array> $rows
     *
     * @return list<array>
     */
    public function hydrate(array $rows, bool $trashed = false): array
    {
        if ($trashed) {
            return array_map(fn (array $row): array => $this->assemble($row, null), $rows);
        }

        $payloads = $this->payloads();
        $legacy   = [];
        foreach ($payloads->chunks($rows) as $chunk) {
            $fields = $payloads->read($chunk);
            foreach ($chunk as $row) {
                if (isset($fields[(int) $row['id']])) {
                    $legacy[] = $this->assemble($row, $fields[(int) $row['id']]);
                }
            }
        }

        return $legacy;
    }

    /**
     * Assemble one legacy row.
     *
     * The keys and their order are those the old query returned.
     *
     * @param array $entry A row of `query()`: the entry joined to its model name and the usernames.
     * @param array|null $fields The entry's fields as StarDust returned them, or null when it has none. A trashed
     *                           entry has no fields in StarDust, so its row keeps the snapshot text that `query(true)` selected.
     */
    public function assemble(array $entry, ?array $fields): array
    {
        return [
            'id'            => $entry['id'],
            'created_at'    => $entry['created_at'],
            'date_deleted'  => $entry['date_deleted'],
            'model_id'      => $entry['model_id'],
            'model_name'    => $entry['model_name'],
            'model_fields'  => $entry['model_fields'],
            'user_groups'   => $entry['user_groups'],
            'fields'        => $fields === null ? ($entry['fields'] ?? null) : FieldRules::fieldsText($fields),
            'date_modified' => $entry['date_modified'],
            'data_id'       => $entry['data_id'],
            'created_by'    => $entry['created_by'],
            'edited_by'     => $entry['edited_by'],
            'deleted_by'    => $entry['deleted_by'],
        ];
    }

    /**
     * The live entries, or the trashed ones, with the columns a list shows under the names a list uses.
     *
     * A live entry whose create or restore is still in progress, which has no StarDust id yet, is left out.
     * A trashed entry carries its snapshot as `fields`, because trashed fields live in HyperCMS, and its
     * `date_modified` is when that snapshot version was saved, as before, not when the entry was trashed.
     * The tables are `e` for the entry, `m` for its model, `r` for its snapshot, and `c`, `ed` and `d` for the
     * people who created, last edited and deleted it. A user that no longer exists gives NULL.
     */
    public function query(bool $trashed = false): BaseBuilder
    {
        $users  = $this->users();
        $select = [
            'e.id',
            'e.created_at',
            'e.deleted_at AS date_deleted',
            'e.model_id',
            'm.name AS model_name',
            'm.fields AS model_fields',
            'm.user_groups',
            ($trashed ? 'r.created_at' : 'e.updated_at') . ' AS date_modified',
            'e.current_revision_id AS data_id',
            'c.' . $users['name'] . ' AS created_by',
            'ed.' . $users['name'] . ' AS edited_by',
            'd.' . $users['name'] . ' AS deleted_by',
            'e.sd_entry_id',
        ];

        $builder = $this->db()->table('hyper_entries AS e');
        if ($trashed) {
            $select[] = 'r.fields';
            $builder->select($select)->select('COALESCE(LENGTH(r.fields), 0) AS payload_bytes', false);
        } else {
            $select[] = 'e.payload_bytes';
            $builder->select($select);
        }

        $builder
            ->join('hyper_models AS m', 'e.model_id = m.id', 'left')
            ->join($users['table'] . ' AS c', 'e.creator_id = c.' . $users['id'], 'left')
            ->join($users['table'] . ' AS ed', 'e.editor_id = ed.' . $users['id'], 'left')
            ->join($users['table'] . ' AS d', 'e.deleter_id = d.' . $users['id'], 'left');

        if ($trashed) {
            return $builder->join('hyper_entry_revisions AS r', 'e.current_revision_id = r.id', 'left')->where('e.deleted_at IS NOT NULL', null, false);
        }

        return $builder->where('e.deleted_at', null)->where('e.sd_entry_id IS NOT NULL', null, false);
    }

    /**
     * Map a list column to the column it reads, as the old list names did. A name this does not know is
     * passed on as written, so an unknown column is a database error as before.
     */
    public function column(string $name, bool $trashed = false): string
    {
        $users = $this->users();

        return match ($name) {
            'id', 'entries.id'                       => 'e.id',
            'model_id'                               => 'e.model_id',
            'model_name', 'model_data.name'          => 'm.name',
            'fields'                                 => $trashed ? 'r.fields' : 'fields',
            'model_fields'                           => 'm.fields',
            'user_groups'                            => 'm.user_groups',
            'created_by'                             => 'c.' . $users['name'],
            'edited_by'                              => 'ed.' . $users['name'],
            'deleted_by'                             => 'd.' . $users['name'],
            'created_at', 'entries.created_at'       => 'e.created_at',
            'date_modified', 'entry_data.created_at' => $trashed ? 'r.created_at' : 'e.updated_at',
            'data_id'                                => 'e.current_revision_id',
            'date_deleted'                           => 'e.deleted_at',
            default                                  => $name,
        };
    }

    /**
     * The table, key column and name column of the people who create, edit and delete entries.
     *
     * @return array{table: string, id: string, name: string}
     */
    public function users(): array
    {
        /** @var HyperEngine $engine */
        $engine = config('HyperEngine');

        return ['table' => $engine->usersTable, 'id' => $engine->usersIdColumn, 'name' => $engine->usersUsernameColumn];
    }

    private function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    private function payloads(): EntryPayloads
    {
        return $this->payloads ??= new EntryPayloads();
    }
}
