<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;
use StarDust\StarDust;
use StarDust\Write\BulkChunkResult;
use StarDust\Write\BulkIngestOptions;
use StarDust\Write\EntryPayload;
use Throwable;

/**
 * Writes the current fields of every live entry to StarDust 0.3, then records the StarDust entry id in
 * `hyper_entries.sd_entry_id` and clears the payload of that entry's current version, so the current
 * fields exist in one place. A trashed entry is not written, and keeps its snapshot.
 *
 * An entry is written once. A rerun takes only the entries that have no `sd_entry_id` yet. If the process
 * dies between the StarDust write and the update that records it, StarDust holds an entry nothing points
 * at, which costs space and nothing else.
 */
final class PayloadWriter
{
    /**
     * The most entries handed to StarDust in one call.
     */
    private const CHUNK_ENTRIES = 1000;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly StarDust $engine,
        private readonly int $tenantId,
    ) {
    }

    /**
     * With a `$deadline`, a time from `microtime(true)`, it stops after the chunk that passes it and answers `complete` as false.
     * Entries are found by having no StarDust id yet, so calling it again carries on where it stopped.
     *
     * @return array{written: int, skipped: list<array{id: int, reason: string}>, complete: bool}
     */
    public function write(?float $deadline = null): array
    {
        $packet  = (int) $this->engine->pdo()->query('SELECT @@max_allowed_packet')->fetchColumn();
        $budget  = max(1024 * 1024, min(Source::BATCH_BYTES, intdiv($packet, 4)));
        $written = 0;
        $skipped = [];
        $lastId  = 0;

        while (true) {
            $candidates = $this->db->query(
                'SELECT e.id, e.current_revision_id AS revision_id, m.sd_model_id, LENGTH(r.fields) AS size
                FROM ' . $this->db->prefixTable('hyper_entries') . ' e
                JOIN ' . $this->db->prefixTable('hyper_models') . ' m ON m.id = e.model_id
                JOIN ' . $this->db->prefixTable('hyper_entry_revisions') . ' r ON r.id = e.current_revision_id
                WHERE e.deleted_at IS NULL AND e.sd_entry_id IS NULL AND e.id > ?
                ORDER BY e.id ASC LIMIT ' . self::CHUNK_ENTRIES,
                [$lastId]
            )->getResultArray();
            if ($candidates === []) {
                return ['written' => $written, 'skipped' => $skipped, 'complete' => true];
            }

            $take  = [];
            $bytes = 0;
            foreach ($candidates as $row) {
                if ($take !== [] && $bytes + (int) $row['size'] > $budget) {
                    break;
                }
                $take[] = $row;
                $bytes += (int) $row['size'];
            }
            $lastId = (int) end($take)['id'];

            $texts = [];
            foreach ($this->db->query('SELECT id, fields FROM ' . $this->db->prefixTable('hyper_entry_revisions') . ' WHERE id IN (' . implode(',', array_map(static fn (array $r): int => (int) $r['revision_id'], $take)) . ')')->getResultArray() as $r) {
                $texts[(int) $r['id']] = $r['fields'];
            }

            $payloads = [];
            $rows     = [];
            foreach ($take as $row) {
                $id = (int) $row['id'];
                if ($row['sd_model_id'] === null) {
                    throw new \RuntimeException("The model of entry $id is not registered in StarDust yet.");
                }

                $text = $texts[(int) $row['revision_id']] ?? null;
                if ((int) $row['size'] * 2 >= $packet) {
                    $skipped[] = ['id' => $id, 'reason' => 'its text does not fit max_allowed_packet'];

                    continue;
                }

                $fields = Payload::fieldsFor($text);
                if ($fields === null) {
                    $skipped[] = ['id' => $id, 'reason' => 'its current version is not an object'];

                    continue;
                }

                $payloads[] = new EntryPayload($this->tenantId, (int) $row['sd_model_id'], $fields);
                $rows[]     = ['id' => $id, 'revision_id' => (int) $row['revision_id'], 'bytes' => (int) $row['size']];
            }

            $handed = $this->hand($payloads, $rows, $skipped);

            $this->db->transStart();
            foreach ($handed as [$row, $sdId]) {
                $this->record($row, $sdId);
                $written++;
            }
            $this->db->transComplete();
            if (! $this->db->transStatus()) {
                throw new \RuntimeException('Recording the StarDust entry ids failed: ' . ($this->db->error()['message'] ?? 'unknown error'));
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                return ['written' => $written, 'skipped' => $skipped, 'complete' => false];
            }
        }
    }

    /**
     * Give the payloads to StarDust and pair each returned id with its entry by order.
     *
     * A chunk StarDust rolled back is written again one entry at a time, so the entry it refuses is named
     * and the rest still go in.
     *
     * @param list<EntryPayload> $payloads
     * @param list<array{id: int, revision_id: int, bytes: int}> $rows
     * @param list<array{id: int, reason: string}> $skipped
     *
     * @return list<array{0: array{id: int, revision_id: int, bytes: int}, 1: int}>
     */
    private function hand(array $payloads, array $rows, array &$skipped): array
    {
        if ($payloads === []) {
            return [];
        }

        $result = $this->engine->bulkWrite($payloads, new BulkIngestOptions(chunkSize: count($payloads)));

        $paired = [];
        $offset = 0;
        foreach ($result->chunks as $chunk) {
            if ($chunk->outcome === BulkChunkResult::OUTCOME_COMMITTED) {
                foreach ($chunk->entryIds as $i => $sdId) {
                    $paired[] = [$rows[$offset + $i], (int) $sdId];
                }
            } else {
                foreach (array_slice($payloads, $offset, $chunk->chunkSize) as $i => $payload) {
                    try {
                        $paired[] = [$rows[$offset + $i], $this->engine->write($payload)->entryId];
                    } catch (Throwable $e) {
                        $skipped[] = ['id' => $rows[$offset + $i]['id'], 'reason' => 'StarDust refused it. ' . $e->getMessage()];
                    }
                }
            }
            $offset += $chunk->chunkSize;
        }

        return $paired;
    }

    /**
     * @param array{id: int, revision_id: int, bytes: int} $row
     */
    private function record(array $row, int $sdId): void
    {
        $this->db->query('UPDATE ' . $this->db->prefixTable('hyper_entries') . ' SET sd_entry_id = ?, payload_bytes = ? WHERE id = ?', [$sdId, $row['bytes'], $row['id']]);
        $this->db->query('UPDATE ' . $this->db->prefixTable('hyper_entry_revisions') . ' SET fields = NULL WHERE id = ?', [$row['revision_id']]);
    }
}
