<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;
use StarDust\Exception\UnsupportedServerException;
use StarDust\StarDust;
use Throwable;

/**
 * Looks at the old database and the server and reports what the upgrade will meet. It writes nothing,
 * so it is also what `--dry-run` prints.
 */
final class Preflight
{
    /**
     * The longest field id StarDust 0.3 registers, in bytes.
     */
    public const MAX_FIELD_BYTES = 128;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Source $source,
        private readonly StarDust $engine,
    ) {
    }

    /**
     * @return array{
     *     layout: string,
     *     server: array{version: string, supported: bool, message: ?string},
     *     packet: array{maxAllowed: int, largestPayload: int, fits: bool},
     *     models: list<array{id: int, name: string, live: int, trashed: int}>,
     *     oddVersions: list<int>,
     *     notObjectVersions: list<int>,
     *     repeatedVersions: list<int>,
     *     entriesWithoutVersion: int,
     *     fieldProblems: list<array{model: string, problem: string}>,
     *     entryQueries: list<array{model: string, field: string}>
     * }
     */
    public function run(): array
    {
        $odd = $notObject = $repeated = [];
        $this->source->eachRow('entry_data', ['fields'], static function (array $row) use (&$odd, &$notObject, &$repeated): void {
            $converted = Payload::convert($row['fields'], $isOdd, $twice);
            if ($isOdd) {
                $odd[] = (int) $row['id'];

                return;
            }
            if ($twice) {
                $repeated[] = (int) $row['id'];
            }
            if (! Payload::isBlank($converted) && Payload::fieldsFor($converted) === null) {
                $notObject[] = (int) $row['id'];
            }
        });

        [$problems, $queries] = $this->inspectModels();

        return [
            'layout'                => $this->source->layout(),
            'server'                => $this->server(),
            'packet'                => $this->packet(),
            'models'                => $this->models(),
            'oddVersions'           => $odd,
            'notObjectVersions'     => $notObject,
            'repeatedVersions'      => $repeated,
            'entriesWithoutVersion' => $this->entriesWithoutVersion(),
            'fieldProblems'         => $problems,
            'entryQueries'          => $queries,
        ];
    }

    /**
     * @return array{version: string, supported: bool, message: ?string}
     */
    private function server(): array
    {
        $version = (string) $this->db->getVersion();

        try {
            $this->engine->serverEngine();
        } catch (UnsupportedServerException $e) {
            return ['version' => $version, 'supported' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['version' => $version, 'supported' => false, 'message' => $e->getMessage()];
        }

        return ['version' => $version, 'supported' => true, 'message' => null];
    }

    /**
     * @return array{maxAllowed: int, largestPayload: int, fits: bool}
     */
    private function packet(): array
    {
        $max     = (int) $this->engine->pdo()->query('SELECT @@max_allowed_packet')->fetchColumn();
        $largest = (int) ($this->db->query('SELECT MAX(LENGTH(fields)) AS size FROM entry_data')->getRow()->size ?? 0);

        // The text travels as JSON with some overhead of its own, so it needs a margin.
        return ['maxAllowed' => $max, 'largestPayload' => $largest, 'fits' => $largest * 2 < $max];
    }

    /**
     * @return list<array{id: int, name: string, live: int, trashed: int}>
     */
    private function models(): array
    {
        $rows = $this->db->query(
            'SELECT m.id, d.name,
                (SELECT COUNT(*) FROM entries e WHERE e.model_id = m.id AND e.deleted_at IS NULL) AS live,
                (SELECT COUNT(*) FROM entries e WHERE e.model_id = m.id AND e.deleted_at IS NOT NULL) AS trashed
            FROM models m
            LEFT JOIN (' . $this->source->currentVersions('models') . ') c ON c.owner_id = m.id
            LEFT JOIN model_data d ON d.id = c.version_id
            ORDER BY m.id'
        )->getResultArray();

        return array_map(static fn (array $r): array => [
            'id'      => (int) $r['id'],
            'name'    => (string) ($r['name'] ?? '(no version)'),
            'live'    => (int) $r['live'],
            'trashed' => (int) $r['trashed'],
        ], $rows);
    }

    private function entriesWithoutVersion(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) AS n FROM entries e WHERE NOT EXISTS (SELECT 1 FROM entry_data d WHERE d.entry_id = e.id)')->getRow()->n;
    }

    /**
     * Read the current definition of every model for field ids StarDust would refuse or that repeat,
     * and for form fields whose data query reads the `entries` table.
     *
     * @return array{0: list<array{model: string, problem: string}>, 1: list<array{model: string, field: string}>}
     */
    private function inspectModels(): array
    {
        $problems = $queries = [];

        $rows = $this->db->query(
            'SELECT m.id, d.name, d.fields FROM models m
            JOIN (' . $this->source->currentVersions('models') . ') c ON c.owner_id = m.id
            JOIN model_data d ON d.id = c.version_id ORDER BY m.id'
        )->getResultArray();

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $seen = [];
            foreach (self::definitionFields($row['fields']) as $field) {
                $id = $field['id'] ?? null;
                if (! is_scalar($id) || (string) $id === '') {
                    $problems[] = ['model' => $name, 'problem' => 'a field has no id'];

                    continue;
                }
                $id = (string) $id;
                if (strlen($id) > self::MAX_FIELD_BYTES) {
                    $problems[] = ['model' => $name, 'problem' => "field id '" . mb_strimwidth($id, 0, 40, '...') . "' is longer than " . self::MAX_FIELD_BYTES . ' bytes'];
                }
                if (isset($seen[$id])) {
                    $problems[] = ['model' => $name, 'problem' => "field id '$id' is used more than once"];
                }
                $seen[$id] = true;

                if (self::readsEntries($field['options'] ?? null)) {
                    $queries[] = ['model' => $name, 'field' => $id];
                }
            }
        }

        return [$problems, $queries];
    }

    /**
     * The fields of a model definition, or none when the text is not a list of fields.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitionFields(?string $json): array
    {
        $data = json_decode((string) $json, true);
        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    private static function readsEntries(mixed $options): bool
    {
        return is_array($options)
            && ($options['type'] ?? null) === 'data'
            && strtolower((string) ($options['content']['table'] ?? '')) === 'entries';
    }
}
