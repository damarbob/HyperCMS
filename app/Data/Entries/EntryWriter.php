<?php

namespace App\Data\Entries;

use App\Data\LookupException;
use App\Data\ModelStore;
use App\Data\NamedLock;
use App\Data\WriteVersions;
use App\Libraries\Upgrade\Payload;
use App\Libraries\Upgrade\Preflight;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use StarDust\Exception\EntryNotFoundException;
use stdClass;
use Throwable;

/**
 * Every write to an entry. EntryStore hands create, update, trash, restore, emptyTrash and
 * clearHistory here.
 *
 * CodeIgniter talks to MySQL through MySQLi and StarDust needs PDO, so the two cannot share a
 * transaction. Every write therefore runs in one order, which leaves a repairable state when the
 * process dies halfway:
 *
 * 1. The HyperCMS rows, in one transaction. The new payload waits in its revision row.
 * 2. StarDust.
 * 3. The HyperCMS rows again, to record the StarDust id, clear the revision's payload, and raise the
 *    write versions. The versions rise last, so a list built between the steps is never cached under
 *    the new number with the old state.
 *
 * One writer at a time touches an entry, through the MySQL named lock `hyper_entry_<id>`. A live row
 * whose `sd_entry_id` is NULL is a create or restore in progress, and `hyper:repair` finishes
 * whatever a crash left.
 */
class EntryWriter
{
    public function __construct(
        private ?BaseConnection $db = null,
        private ?EntryEngine $engine = null,
        private ?WriteVersions $versions = null,
        private ?NamedLock $locks = null,
        private ?ModelStore $models = null,
    ) {
    }

    /**
     * Create an entry and return its public id.
     *
     * @param array $data The `model_id` and the `fields` as the admin sends them, a JSON object or the older list.
     *
     * @throws InvalidArgumentException When the model id or the fields cannot be used.
     * @throws LookupException          When there is no such model.
     */
    public function create(array $data, int $userId): int
    {
        if (! isset($data['model_id']) || ! is_numeric($data['model_id'])) {
            throw new InvalidArgumentException('An entry needs a `model_id`.');
        }
        $modelId         = (int) $data['model_id'];
        [$text, $fields] = $this->payload($data['fields'] ?? null);

        // The model is registered before anything is written, so a failure here leaves nothing behind.
        $model     = $this->model($modelId);
        $sdModelId = $model['sd_model_id'] !== null ? (int) $model['sd_model_id'] : $this->models()->ensureRegistered($modelId);

        $db  = $this->db();
        $now = $this->now();

        // Step 1. The entry has no StarDust id yet, so lists skip it until step 3.
        [$id, $revisionId] = $this->transact(function () use ($db, $modelId, $userId, $text, $now): array {
            $db->table('hyper_entries')->insert([
                'model_id'      => $modelId,
                'creator_id'    => $userId,
                'editor_id'     => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
                'payload_bytes' => strlen($text),
            ]);
            $id = (int) $db->insertID();

            $revisionId = $this->insertRevision($id, $text, $userId, $now);
            $db->table('hyper_entries')->where('id', $id)->update(['current_revision_id' => $revisionId]);

            return [$id, $revisionId];
        });

        $this->locks()->run(NamedLock::forEntry($id), function () use ($id, $revisionId, $modelId, $sdModelId, $fields): void {
            // Step 2.
            $sdId = $this->engine()->write($sdModelId, $fields);

            // Step 3.
            $this->recordLive($id, $sdId, $revisionId);
            $this->versions()->bump([$modelId]);
        });

        return $id;
    }

    /**
     * Save a new version of an entry.
     *
     * @throws InvalidArgumentException When the fields cannot be used.
     * @throws LookupException          When there is no such entry.
     */
    public function update(int $id, array $data, int $userId): void
    {
        [$text, $fields] = $this->payload($data['fields'] ?? null);

        $this->locks()->run(NamedLock::forEntry($id), function () use ($id, $userId, $text, $fields): void {
            $entry = $this->entryRow($id);
            if ($entry === null) {
                throw new LookupException("Entry $id does not exist.");
            }

            $db  = $this->db();
            $now = $this->now();

            // A trashed entry has no live StarDust row. Its new version becomes the snapshot a restore reads.
            if ($entry['deleted_at'] !== null) {
                $this->transact(function () use ($id, $userId, $text, $now): void {
                    $this->supersede($id, $userId, $text, $now);
                });
                $this->versions()->bump([(int) $entry['model_id']]);

                return;
            }

            $current = $this->currentRevision($entry);

            // Step 1. The live payload is copied out of StarDust into the version it belongs to, unless that
            // version already holds one. That means an earlier save stopped before step 3, and the payload it
            // holds is the one that was meant to be live.
            $copy = null;
            if ($entry['sd_entry_id'] !== null && $current !== null && $current['fields'] === null) {
                $copy = $this->snapshotOf((int) $entry['sd_entry_id'], $id);
            }

            $revisionId = $this->transact(function () use ($db, $id, $userId, $text, $now, $current, $copy): int {
                if ($copy !== null) {
                    $db->table('hyper_entry_revisions')->where('id', (int) $current['id'])->update(['fields' => $copy]);
                }

                return $this->supersede($id, $userId, $text, $now);
            });

            // Step 2.
            $sdId = $this->push($entry, $fields);

            // Step 3.
            $this->recordLive($id, $sdId, $revisionId);
            $this->versions()->bump([(int) $entry['model_id']]);
        });
    }

    /**
     * Move entries to the trash.
     */
    public function trash(array $ids, int $userId): void
    {
        foreach ($this->ids($ids) as $id) {
            $this->trashOne($id, $userId);
        }
    }

    /**
     * Bring entries back from the trash.
     *
     * An entry whose model is trashed or gone stays in the trash, because it would come back with no model to show it under.
     */
    public function restore(array $ids): void
    {
        foreach ($this->ids($ids) as $id) {
            $this->restoreOne($id, true);
        }
    }

    /**
     * Delete the trashed entries for good, and their saved versions.
     *
     * StarDust 0.3 has no hard delete, so the rows it holds for them stay soft-deleted and nothing points at them.
     */
    public function emptyTrash(): void
    {
        $db     = $this->db();
        $lastId = 0;

        while (true) {
            $rows = $db->query(
                'SELECT id, model_id, sd_entry_id FROM hyper_entries WHERE deleted_at IS NOT NULL AND id > ? ORDER BY id LIMIT 500',
                [$lastId]
            )->getResultArray();
            if ($rows === []) {
                return;
            }
            $lastId = (int) end($rows)['id'];

            // A trash that stopped before StarDust was told is finished here, because nothing will point at the row afterwards.
            foreach ($rows as $row) {
                if ($row['sd_entry_id'] !== null) {
                    $this->engine()->delete((int) $row['sd_entry_id']);
                }
            }

            $ids = implode(',', array_map(static fn (array $row): int => (int) $row['id'], $rows));

            // The entries go first, and only those still in the trash. A restore running at the same moment
            // wins, and its entry keeps its versions because the second statement only takes versions with no entry.
            $this->transact(function () use ($db, $ids): void {
                $db->query("DELETE FROM hyper_entries WHERE id IN ($ids) AND deleted_at IS NOT NULL");
                $db->query("DELETE r FROM hyper_entry_revisions r LEFT JOIN hyper_entries e ON e.id = r.entry_id WHERE r.entry_id IN ($ids) AND e.id IS NULL");
            });

            $this->versions()->bump(array_map(static fn (array $row): int => (int) $row['model_id'], $rows));
        }
    }

    /**
     * Trash the live entries of the given models, as ModelStore does when it trashes a model.
     *
     * Each entry is trashed as `trash()` does, with its payload copied out of StarDust first.
     */
    public function trashForModels(array $modelIds, int $userId): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Bring back the entries of the given models, as ModelStore does when it restores a trashed model.
     *
     * The entries trashed before the model was, come back with it, as they did on StarDust 0.2.
     */
    public function restoreForModels(array $modelIds): void
    {
        throw $this->notPorted(__FUNCTION__);
    }

    /**
     * Delete every saved version of an entry except the current one.
     *
     * @return int|null The number of versions deleted, or null when the entry has no versions.
     */
    public function clearHistory(int|string $entryId): ?int
    {
        $id = (int) $entryId;

        // The lock keeps a save from adding a version while the older ones go.
        return $this->locks()->run(NamedLock::forEntry($id), function () use ($id): ?int {
            $db     = $this->db();
            $newest = $db->table('hyper_entry_revisions')->selectMax('id', 'newest')->where('entry_id', $id)->get()->getRow()->newest ?? null;
            if ($newest === null) {
                return null;
            }

            // The current version is the newest one, and it is kept in any case because it may be the only copy of the payload.
            $keep = [(int) $newest];
            $current = $db->table('hyper_entries')->select('current_revision_id')->where('id', $id)->get()->getRow()->current_revision_id ?? null;
            if ($current !== null) {
                $keep[] = (int) $current;
            }

            $db->table('hyper_entry_revisions')->where('entry_id', $id)->whereNotIn('id', array_unique($keep))->delete();

            return $db->affectedRows();
        });
    }

    /**
     * Trash one live entry. An entry that is missing or already in the trash is left as it is.
     *
     * The live payload is copied out of StarDust into the entry's current version first, so the trash view and a
     * restore read HyperCMS rows only.
     *
     * @return int|null The model id when the entry was trashed.
     */
    protected function trashOne(int $id, int $userId, bool $bump = true): ?int
    {
        return $this->locks()->run(NamedLock::forEntry($id), function () use ($id, $userId, $bump): ?int {
            $entry = $this->entryRow($id);
            if ($entry === null || $entry['deleted_at'] !== null) {
                return null;
            }

            $db      = $this->db();
            $now     = $this->now();
            $current = $this->currentRevision($entry);
            $sdId    = $entry['sd_entry_id'] !== null ? (int) $entry['sd_entry_id'] : null;

            // A version that holds a payload already is the one to keep. It is a save that has not reached StarDust yet.
            $copy = $sdId !== null && $current !== null && $current['fields'] === null ? $this->snapshotOf($sdId, $id) : null;

            // Step 1.
            $this->transact(function () use ($db, $id, $userId, $now, $current, $copy): void {
                if ($copy !== null) {
                    $db->table('hyper_entry_revisions')->where('id', (int) $current['id'])->update(['fields' => $copy]);
                }
                $db->table('hyper_entries')->where('id', $id)->update(['deleted_at' => $now, 'deleter_id' => $userId, 'updated_at' => $now]);
            });

            // Step 2.
            if ($sdId !== null) {
                $this->engine()->delete($sdId);
            }

            // Step 3. A trashed entry with a StarDust id is one StarDust has not been told about yet.
            $db->table('hyper_entries')->where('id', $id)->update(['sd_entry_id' => null]);
            if ($bump) {
                $this->versions()->bump([(int) $entry['model_id']]);
            }

            return (int) $entry['model_id'];
        });
    }

    /**
     * Bring one entry back from the trash by writing its snapshot to StarDust as a new entry, because StarDust
     * cannot bring a deleted one back. An entry that is not in the trash is left as it is.
     *
     * @param bool $needsModel Leave the entry in the trash when its model is trashed or gone.
     *
     * @return int|null The model id when the entry was restored.
     */
    protected function restoreOne(int $id, bool $needsModel, bool $bump = true): ?int
    {
        return $this->locks()->run(NamedLock::forEntry($id), function () use ($id, $needsModel, $bump): ?int {
            $entry = $this->entryRow($id);
            if ($entry === null || $entry['deleted_at'] === null) {
                return null;
            }
            if ($entry['found_model_id'] === null || ($needsModel && $entry['model_deleted_at'] !== null)) {
                return null;
            }

            $db      = $this->db();
            $now     = $this->now();
            $current = $this->currentRevision($entry);
            $text    = $current['fields'] ?? null;
            $fields  = $this->fieldsOf($text);

            // Registered before anything changes, so a failure here leaves the entry in the trash.
            $sdModelId = $entry['sd_model_id'] !== null ? (int) $entry['sd_model_id'] : $this->models()->ensureRegistered((int) $entry['model_id']);

            // Step 1. Live again, with no StarDust id until step 3, so lists skip it.
            $restored = $this->transact(function () use ($db, $id, $now, $text): bool {
                $db->table('hyper_entries')->where('id', $id)->where('deleted_at IS NOT NULL', null, false)->update([
                    'deleted_at'    => null,
                    'sd_entry_id'   => null,
                    'updated_at'    => $now,
                    'payload_bytes' => strlen((string) ($text ?? '{}')),
                ]);

                return $db->affectedRows() > 0;
            });
            if (! $restored) {
                return null;
            }

            // Step 2.
            $sdId = $this->engine()->write($sdModelId, $fields);

            // Step 3.
            if ($current === null) {
                $db->table('hyper_entries')->where('id', $id)->update(['sd_entry_id' => $sdId]);
            } else {
                $this->recordLive($id, $sdId, (int) $current['id']);
            }
            if ($bump) {
                $this->versions()->bump([(int) $entry['model_id']]);
            }

            return (int) $entry['model_id'];
        });
    }

    /**
     * @return list<int>
     */
    protected function ids(array $ids): array
    {
        return array_values(array_filter(array_unique(array_map('intval', $ids)), static fn (int $id): bool => $id > 0));
    }

    /**
     * Turn what the admin sent as `fields` into the text a version stores and the array StarDust takes.
     *
     * The older list, `[{"id":"title","value":"x"}]`, becomes the object `{"title":"x"}`. Nothing at all, `""`,
     * `[]` and `{}` are a blank entry, which StarDust holds as `[]` and a version holds as `{}`.
     *
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @throws InvalidArgumentException When the text is not an object or a list that can become one, or a key
     *                                  is empty or longer than StarDust accepts.
     */
    protected function payload(mixed $raw): array
    {
        if (is_array($raw) || $raw instanceof stdClass) {
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        if ($raw !== null && ! is_string($raw)) {
            throw new InvalidArgumentException('The `fields` of an entry must be a JSON object.');
        }
        if (Payload::isBlank($raw)) {
            return ['{}', []];
        }

        $data = json_decode($raw);
        if (is_array($data)) {
            $data = Payload::keyValue($raw);
            if (! $data instanceof stdClass) {
                throw new InvalidArgumentException('The `fields` of an entry must be a JSON object, or a list of id and value pairs.');
            }
        }
        if (! $data instanceof stdClass) {
            throw new InvalidArgumentException('The `fields` of an entry must be a JSON object.');
        }

        foreach (array_keys((array) $data) as $key) {
            if ((string) $key === '' || strlen((string) $key) > Preflight::MAX_FIELD_BYTES) {
                throw new InvalidArgumentException('A field id must be between 1 and ' . Preflight::MAX_FIELD_BYTES . ' bytes long.');
            }
        }

        $text = Payload::encode($data);

        return [$text, json_decode($text, true)];
    }

    /**
     * Read the stored text of a version as the array StarDust takes, with a missing text as a blank entry.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When the text cannot be made into an object.
     */
    protected function fieldsOf(?string $text): array
    {
        return $this->payload($text)[1];
    }

    /**
     * Give StarDust the payload and return the StarDust id the entry now has.
     *
     * An entry that has none is written. An entry StarDust no longer finds is written again, and gets the new id.
     *
     * @param array<string, mixed> $entry  The `hyper_entries` row with `sd_model_id` of its model.
     * @param array<string, mixed> $fields
     */
    protected function push(array $entry, array $fields): int
    {
        if ($entry['sd_entry_id'] !== null) {
            try {
                $this->engine()->update((int) $entry['sd_entry_id'], $fields);

                return (int) $entry['sd_entry_id'];
            } catch (EntryNotFoundException) {
                log_message('warning', 'StarDust has no live entry {sd} for entry {id}, so it is written again.', ['sd' => $entry['sd_entry_id'], 'id' => $entry['id']]);
            }
        }

        $sdModelId = $entry['sd_model_id'] !== null ? (int) $entry['sd_model_id'] : $this->models()->ensureRegistered((int) $entry['model_id']);

        return $this->engine()->write($sdModelId, $fields);
    }

    /**
     * Read the live payload of an entry from StarDust as the text a version stores.
     *
     * StarDust not having it leaves nothing to keep, so the version is blank.
     */
    protected function snapshotOf(int $sdEntryId, int $entryId): string
    {
        $live = $this->engine()->get($sdEntryId);
        if ($live === null) {
            log_message('warning', 'StarDust holds no live payload for entry {id}, so its saved version is blank.', ['id' => $entryId]);

            return '{}';
        }

        return Payload::encode((object) $live);
    }

    /**
     * Add a version holding the text, make it the entry's current one, and stamp who saved it and when.
     * Runs inside the caller's transaction.
     *
     * @return int The id of the new version.
     */
    protected function supersede(int $entryId, int $userId, string $text, string $now): int
    {
        $revisionId = $this->insertRevision($entryId, $text, $userId, $now);

        $this->db()->table('hyper_entries')->where('id', $entryId)->update([
            'current_revision_id' => $revisionId,
            'editor_id'           => $userId,
            'updated_at'          => $now,
            'payload_bytes'       => strlen($text),
        ]);

        return $revisionId;
    }

    protected function insertRevision(int $entryId, ?string $text, int $userId, string $now): int
    {
        $this->db()->table('hyper_entry_revisions')->insert([
            'entry_id'   => $entryId,
            'fields'     => $text,
            'creator_id' => $userId,
            'created_at' => $now,
        ]);

        return (int) $this->db()->insertID();
    }

    /**
     * Step 3 of a write that leaves the entry live. StarDust has the payload, so the entry records its id and
     * its version stops holding a copy.
     */
    protected function recordLive(int $entryId, int $sdEntryId, int $revisionId): void
    {
        $db = $this->db();

        $this->transact(function () use ($db, $entryId, $sdEntryId, $revisionId): void {
            $db->table('hyper_entries')->where('id', $entryId)->update(['sd_entry_id' => $sdEntryId]);
            $db->table('hyper_entry_revisions')->where('id', $revisionId)->update(['fields' => null]);
        });
    }

    /**
     * The `hyper_entries` row of an entry with the StarDust id of its model, or null.
     *
     * @return array<string, mixed>|null
     */
    protected function entryRow(int $id): ?array
    {
        return $this->db()->query(
            'SELECT e.*, m.sd_model_id, m.deleted_at AS model_deleted_at, m.id AS found_model_id
            FROM hyper_entries e LEFT JOIN hyper_models m ON m.id = e.model_id
            WHERE e.id = ?',
            [$id]
        )->getRowArray();
    }

    /**
     * The current version of an entry, or null when it points at none.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>|null
     */
    protected function currentRevision(array $entry): ?array
    {
        if ($entry['current_revision_id'] === null) {
            return null;
        }

        return $this->db()->table('hyper_entry_revisions')->where('id', (int) $entry['current_revision_id'])->get()->getRowArray();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws LookupException When there is no such model.
     */
    protected function model(int $modelId): array
    {
        $row = $this->db()->table('hyper_models')->select('id, sd_model_id, deleted_at')->where('id', $modelId)->get()->getRowArray();
        if ($row === null) {
            throw new LookupException("Model $modelId does not exist.");
        }

        return $row;
    }

    /**
     * Run the callback in one MySQLi transaction, and roll it back when it throws.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function transact(callable $callback): mixed
    {
        $db = $this->db();
        $db->transBegin();

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        if (! $db->transStatus()) {
            $db->transRollback();

            throw new RuntimeException('Could not save the entry: ' . ($db->error()['message'] ?? 'unknown error'));
        }
        $db->transCommit();

        return $result;
    }

    protected function now(): string
    {
        return Time::now()->toDateTimeString();
    }

    protected function db(): BaseConnection
    {
        return $this->db ??= db_connect();
    }

    protected function engine(): EntryEngine
    {
        return $this->engine ??= new StarDustEntries(service('stardust'), (int) config('HyperEngine')->tenantId);
    }

    protected function versions(): WriteVersions
    {
        return $this->versions ??= new WriteVersions($this->db());
    }

    protected function locks(): NamedLock
    {
        return $this->locks ??= new NamedLock($this->db());
    }

    protected function models(): ModelStore
    {
        return $this->models ??= new ModelStore($this->db(), null, $this->versions(), $this->locks(), $this);
    }

    private function notPorted(string $method): LogicException
    {
        return new LogicException(static::class . '::' . $method . '() is not ported to StarDust 0.3 yet.');
    }
}
