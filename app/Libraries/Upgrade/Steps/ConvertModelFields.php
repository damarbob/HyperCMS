<?php

namespace App\Libraries\Upgrade\Steps;

use App\Data\ModelStore;
use App\Libraries\Upgrade\Convert\LabelField;
use App\Libraries\Upgrade\Convert\ModelFields;
use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\SchemaStore;
use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Step 1. Rewrites the field definition of every model, and of every saved version of it, into version 2.
 *
 * A relation found in an old data query also tells which field of its target model was shown as the label, so the
 * target gets that field as its label field when it has none. The models go in id order and the step remembers the last
 * one it finished, with the change and the mark written together, so an interrupted run goes on where it stopped and
 * never converts a model twice.
 */
final class ConvertModelFields implements ConversionStep
{
    /**
     * Types that exist only in version 2. A definition with one of them is already converted. A definition with none of
     * them is converted again harmlessly, because its fields convert to themselves.
     */
    private const VERSION_2_ONLY = ['boolean', 'choice', 'longtext', 'richtext', 'code', 'datetime', 'relation', 'model', 'hook'];

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly ModelFields $converter,
        private readonly ?ModelStore $models = null,
    ) {
    }

    public function number(): int
    {
        return 1;
    }

    public function name(): string
    {
        return 'Convert the field definitions of the models';
    }

    public function run(SchemaStore $store, bool $dryRun): array
    {
        $rows     = $this->db->table('hyper_models')->select('id, name, fields, label_field, deleted_at')->orderBy('id')->get()->getResultArray();
        $modelIds = [];
        foreach ($rows as $row) {
            if ($row['deleted_at'] === null && ! isset($modelIds[(string) $row['name']])) {
                $modelIds[(string) $row['name']] = (int) $row['id'];
            }
        }

        // The first pass converts every definition in memory, to learn which field each relation showed as its label.
        $converted = [];
        $hints     = [];
        foreach ($rows as $row) {
            $old = self::decode($row['fields']);
            if ($old === null || self::isVersion2($old)) {
                continue;
            }
            $result                     = $this->converter->convert($old, $modelIds);
            $converted[(int) $row['id']] = $result;
            foreach ($result['labels'] as $label) {
                $hints[$label['model']][] = $label['field'];
            }
        }

        $cursor   = $store->cursor($this->number()) ?? 0;
        $changed  = 0;
        $problems = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($id <= $cursor || ! isset($converted[$id])) {
                continue;
            }

            $result = $converted[$id];
            foreach ($result['problems'] as $problem) {
                $problems[] = "Model {$row['name']} ($id), field {$problem['field']}: {$problem['problem']}";
            }
            $changed++;
            if ($dryRun) {
                continue;
            }

            $label = $row['label_field'] === null && isset($hints[$id]) ? LabelField::pick($result['fields'], $hints[$id]) : null;

            $this->db->transStart();
            $update = ['fields' => json_encode($result['fields'], self::JSON_FLAGS)];
            if ($label !== null) {
                $update['label_field'] = $label;
            }
            $this->db->table('hyper_models')->where('id', $id)->update($update);
            $this->convertRevisions($id, $modelIds);
            $store->setCursor($this->number(), $id);
            $this->db->transComplete();

            // StarDust has to learn which fields are still filterable and as what.
            if ($row['deleted_at'] === null) {
                try {
                    ($this->models ?? service('modelStore'))->syncFields($id);
                } catch (Throwable $e) {
                    $problems[] = "Model {$row['name']} ($id): StarDust could not update its fields. " . $e->getMessage();
                }
            }
        }

        return ['changed' => $changed, 'problems' => $problems];
    }

    /**
     * @param array<string, int> $modelIds
     */
    private function convertRevisions(int $modelId, array $modelIds): void
    {
        $revisions = $this->db->table('hyper_model_revisions')->select('id, fields')->where('model_id', $modelId)->get()->getResultArray();
        foreach ($revisions as $revision) {
            $old = self::decode($revision['fields']);
            if ($old === null || self::isVersion2($old)) {
                continue;
            }
            $result = $this->converter->convert($old, $modelIds);
            $this->db->table('hyper_model_revisions')->where('id', (int) $revision['id'])->update(['fields' => json_encode($result['fields'], self::JSON_FLAGS)]);
        }
    }

    /**
     * @return list<mixed>|null The list of fields, or null when the text is not a list.
     */
    private static function decode(?string $json): ?array
    {
        $data = json_decode((string) $json, true);

        return is_array($data) && array_is_list($data) ? $data : null;
    }

    /**
     * Whether a decoded definition already holds a field of a type only version 2 has.
     *
     * @param list<mixed> $fields
     */
    public static function isVersion2(array $fields): bool
    {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $type = $field['type'] ?? null;

            // A number or a file of the old form never carried these keys, and every one of version 2 does.
            if (in_array($type, self::VERSION_2_ONLY, true)
                || ($type === 'number' && array_key_exists('integer', $field))
                || ($type === 'file' && array_key_exists('accept', $field))) {
                return true;
            }
        }

        return false;
    }
}
