<?php

namespace App\Libraries\Upgrade\Steps;

use App\Data\Entries\EntryEngine;
use App\Data\WriteVersions;
use App\Libraries\Upgrade\Convert\EntryValues;
use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\Payload;
use App\Libraries\Upgrade\SchemaStore;
use CodeIgniter\Database\BaseConnection;

/**
 * Step 3. Rewrites the field values of every entry into the shape of its field type, in StarDust for a live entry and in
 * every saved version of it, so restoring an old version needs no reader of the old shapes.
 *
 * The entries go in id order and the step remembers the last chunk it finished. The conversion changes nothing that is
 * already converted, so a chunk that was cut short is simply done again. A value that fits no rule of its type is kept
 * as it was and listed.
 */
final class ConvertEntryValues implements ConversionStep
{
    private const CHUNK = 200;

    /**
     * The definition of each model by id, read once.
     *
     * @var array<int, list<array<string, mixed>>>|null
     */
    private ?array $definitions = null;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly EntryValues $converter,
        private readonly EntryEngine $engine,
        private readonly WriteVersions $versions,
    ) {
    }

    public function number(): int
    {
        return 3;
    }

    public function name(): string
    {
        return 'Convert the values of the entries';
    }

    public function run(SchemaStore $store, bool $dryRun): array
    {
        $cursor   = $store->cursor($this->number()) ?? 0;
        $changed  = 0;
        $problems = [];
        $touched  = [];

        while (true) {
            $entries = $this->db->query(
                'SELECT id, model_id, sd_entry_id, deleted_at FROM hyper_entries WHERE id > ? ORDER BY id LIMIT ' . self::CHUNK,
                [$cursor]
            )->getResultArray();
            if ($entries === []) {
                break;
            }

            foreach ($entries as $entry) {
                $id          = (int) $entry['id'];
                $definitions = $this->definitions()[(int) $entry['model_id']] ?? null;
                $cursor      = $id;
                if ($definitions === null) {
                    continue;
                }

                $did = $this->convertLive($entry, $definitions, $dryRun, $problems) | $this->convertRevisions($id, $definitions, $dryRun, $problems);
                if ($did) {
                    $changed++;
                    $touched[(int) $entry['model_id']] = true;
                }
            }

            if (! $dryRun) {
                $store->setCursor($this->number(), $cursor);
            }
        }

        if (! $dryRun && $touched !== []) {
            $this->versions->bump(array_keys($touched));
        }

        return ['changed' => $changed, 'problems' => $problems];
    }

    /**
     * @param array<string, mixed>       $entry
     * @param list<array<string, mixed>> $definitions
     * @param list<string>               $problems
     */
    private function convertLive(array $entry, array $definitions, bool $dryRun, array &$problems): bool
    {
        if ($entry['deleted_at'] !== null || $entry['sd_entry_id'] === null) {
            return false;
        }

        $live = $this->engine->get((int) $entry['sd_entry_id']);
        if ($live === null) {
            return false;
        }

        $result = $this->converter->convert($live, $definitions);
        foreach ($result['problems'] as $problem) {
            $problems[] = "Entry {$entry['id']}, field {$problem['field']}: {$problem['problem']}";
        }
        if (json_encode($result['fields']) === json_encode($live)) {
            return false;
        }

        if (! $dryRun) {
            $this->engine->update((int) $entry['sd_entry_id'], $result['fields']);
            $this->db->table('hyper_entries')->where('id', (int) $entry['id'])->update(['payload_bytes' => strlen(Payload::encode((object) $result['fields']))]);
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param list<string>               $problems
     */
    private function convertRevisions(int $entryId, array $definitions, bool $dryRun, array &$problems): bool
    {
        $any       = false;
        $revisions = $this->db->table('hyper_entry_revisions')->select('id, fields')->where('entry_id', $entryId)->where('fields IS NOT NULL', null, false)->get()->getResultArray();

        foreach ($revisions as $revision) {
            $old = json_decode((string) $revision['fields'], true);
            if (! is_array($old) || ($old !== [] && array_is_list($old))) {
                continue;
            }

            $result = $this->converter->convert($old, $definitions);
            foreach ($result['problems'] as $problem) {
                $problems[] = "Entry $entryId, version {$revision['id']}, field {$problem['field']}: {$problem['problem']}";
            }
            if (json_encode($result['fields']) === json_encode($old)) {
                continue;
            }

            $any = true;
            if (! $dryRun) {
                $this->db->table('hyper_entry_revisions')->where('id', (int) $revision['id'])->update(['fields' => Payload::encode((object) $result['fields'])]);
            }
        }

        return $any;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function definitions(): array
    {
        if ($this->definitions === null) {
            $this->definitions = [];
            foreach ($this->db->table('hyper_models')->select('id, fields')->get()->getResultArray() as $row) {
                $fields = json_decode((string) $row['fields'], true);
                $this->definitions[(int) $row['id']] = is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
            }
        }

        return $this->definitions;
    }
}
