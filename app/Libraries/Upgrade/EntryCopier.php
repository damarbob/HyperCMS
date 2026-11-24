<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;

/**
 * Copies `entries` and `entry_data` into `hyper_entries` and `hyper_entry_revisions` with the same ids,
 * so a public id, a link or a stored select value keeps pointing at the same entry.
 *
 * Every version is copied with its text in the object format. A live entry's current version keeps
 * its text here until PayloadWriter has handed it to StarDust. A trashed entry's current version keeps
 * its text for good, as the snapshot the trash view and restore read.
 */
final class EntryCopier
{
    /**
     * How many versions are inserted at a time.
     */
    private const INSERT_ROWS = 200;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Source $source,
    ) {
    }

    /**
     * Copy the versions that are not copied yet. With a `$deadline`, a time from `microtime(true)`, it stops after the chunk that
     * passes it and answers `complete` as false. A version is found by not being copied, so a second call carries on.
     *
     * @return array{count: int, odd: list<int>, repeated: list<int>, complete: bool} How many were added, the ids of versions
     *                                                              that hold a list in some other shape and were copied as they are,
     *                                                              and the ids of versions that named one field twice.
     */
    public function copyRevisions(?float $deadline = null): array
    {
        $count    = 0;
        $odd      = [];
        $repeated = [];
        $batch    = [];
        $bytes    = 0;

        $flush = function () use (&$batch, &$bytes, &$count): void {
            if ($batch === []) {
                return;
            }
            $this->db->transStart();
            $this->db->table('hyper_entry_revisions')->insertBatch($batch);
            $this->db->transComplete();
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Copying the entry versions failed: ' . ($this->db->error()['message'] ?? 'unknown error'));
            }
            $count += count($batch);
            $batch = [];
            $bytes = 0;
        };

        $complete = $this->source->eachRow(
            'entry_data',
            ['entry_id', 'fields', 'creator_id', 'created_at'],
            function (array $row) use (&$batch, &$bytes, &$odd, &$repeated, $flush): void {
                $fields = Payload::convert($row['fields'], $isOdd, $twice);
                if ($isOdd) {
                    $odd[] = (int) $row['id'];
                }
                if ($twice) {
                    $repeated[] = (int) $row['id'];
                }

                $batch[] = [
                    'id'         => (int) $row['id'],
                    'entry_id'   => (int) $row['entry_id'],
                    'fields'     => $fields,
                    'creator_id' => (int) $row['creator_id'],
                    'created_at' => $row['created_at'],
                ];
                $bytes += strlen((string) $fields);

                if (count($batch) >= self::INSERT_ROWS || $bytes >= Source::BATCH_BYTES) {
                    $flush();
                }
            },
            'fields',
            'NOT EXISTS (SELECT 1 FROM hyper_entry_revisions r WHERE r.id = entry_data.id)',
            0,
            $deadline
        );
        $flush();

        return ['count' => $count, 'odd' => $odd, 'repeated' => $repeated, 'complete' => $complete];
    }

    /**
     * Copy the entries that are not copied yet, each pointing at its current version.
     *
     * An entry with no saved version, or one whose model was not copied, is left behind. The old version
     * never showed the first, and has no model to show the second under.
     *
     * @return int How many entries were added.
     */
    public function copyEntries(): int
    {
        $this->db->transStart();

        $this->db->query(
            'INSERT INTO hyper_entries (id, model_id, current_revision_id, creator_id, editor_id, deleter_id, created_at, updated_at, deleted_at, payload_bytes)
            SELECT e.id, e.model_id, d.id, e.creator_id, d.creator_id, e.deleter_id, e.created_at, e.updated_at, e.deleted_at, 0
            FROM entries e
            JOIN (' . $this->source->currentVersions('entries') . ') c ON c.owner_id = e.id
            JOIN entry_data d ON d.id = c.version_id
            WHERE EXISTS (SELECT 1 FROM hyper_models m WHERE m.id = e.model_id)
              AND NOT EXISTS (SELECT 1 FROM hyper_entries h WHERE h.id = e.id)'
        );
        $added = $this->db->affectedRows();

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Copying the entries failed: ' . ($this->db->error()['message'] ?? 'unknown error'));
        }

        return $added;
    }
}
