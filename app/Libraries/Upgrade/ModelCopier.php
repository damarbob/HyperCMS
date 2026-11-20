<?php

namespace App\Libraries\Upgrade;

use CodeIgniter\Database\BaseConnection;
use StarDust\Schema\FieldDefinition;
use StarDust\StarDust;

/**
 * Copies `models` and `model_data` into `hyper_models` and `hyper_model_revisions` with the same ids,
 * then registers each model in StarDust as `m{id}`. A model is named after its id because StarDust
 * creates models by name and would turn two HyperCMS models called "Article" into one.
 */
final class ModelCopier
{
    /**
     * @param int $tenantId The one StarDust tenant every HyperCMS model and entry belongs to.
     */
    public function __construct(
        private readonly BaseConnection $db,
        private readonly Source $source,
        private readonly StarDust $engine,
        private readonly int $tenantId,
    ) {
    }

    /**
     * Copy the models and their versions that are not copied yet.
     *
     * @return array{models: int, revisions: int} How many rows were added.
     */
    public function copy(): array
    {
        $this->db->transStart();

        $this->db->query(
            'INSERT INTO hyper_model_revisions (id, model_id, name, fields, `group`, icon, user_groups, creator_id, created_at)
            SELECT d.id, d.model_id, d.name, d.fields, d.`group`, d.icon, d.user_groups, d.creator_id, d.created_at
            FROM model_data d
            WHERE NOT EXISTS (SELECT 1 FROM hyper_model_revisions r WHERE r.id = d.id)'
        );
        $revisions = $this->db->affectedRows();

        // A model with no saved version has no name, so it cannot be shown and is left behind.
        $this->db->query(
            'INSERT INTO hyper_models (id, name, fields, `group`, icon, user_groups, current_revision_id, creator_id, editor_id, deleter_id, created_at, updated_at, deleted_at, write_version)
            SELECT m.id, d.name, d.fields, d.`group`, d.icon, d.user_groups, d.id, m.creator_id, d.creator_id, m.deleter_id, m.created_at, m.updated_at, m.deleted_at, 0
            FROM models m
            JOIN (' . $this->source->currentVersions('models') . ') c ON c.owner_id = m.id
            JOIN model_data d ON d.id = c.version_id
            WHERE NOT EXISTS (SELECT 1 FROM hyper_models h WHERE h.id = m.id)'
        );
        $models = $this->db->affectedRows();

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Copying the models failed: ' . ($this->db->error()['message'] ?? 'unknown error'));
        }

        return ['models' => $models, 'revisions' => $revisions];
    }

    /**
     * Register every model that has no StarDust model yet, with each of its fields as a string.
     *
     * A field id that is empty, longer than StarDust accepts, or repeated is registered once or skipped,
     * and Preflight has already named it. An entry still keeps a value stored under such a key.
     *
     * @return int How many models were registered.
     */
    public function register(): int
    {
        $count = 0;

        foreach ($this->db->query('SELECT id, fields FROM hyper_models WHERE sd_model_id IS NULL ORDER BY id')->getResultArray() as $row) {
            $definition = $this->engine->schemaBuilder()->createModel($this->tenantId, 'm' . $row['id'], self::fieldDefinitions($row['fields']));

            $this->db->query('UPDATE hyper_models SET sd_model_id = ? WHERE id = ?', [$definition->modelId, $row['id']]);
            $count++;
        }

        return $count;
    }

    /**
     * Turn a model's form definition into StarDust fields. Every field is a string that is not filterable,
     * so StarDust stores each value exactly as written and never refuses one for its type.
     *
     * @return list<FieldDefinition>
     */
    public static function fieldDefinitions(?string $definition): array
    {
        $fields = [];
        foreach (Preflight::definitionFields($definition) as $field) {
            $id = $field['id'] ?? null;
            if (! is_scalar($id) || (string) $id === '' || strlen((string) $id) > Preflight::MAX_FIELD_BYTES) {
                continue;
            }
            $fields[(string) $id] ??= new FieldDefinition((string) $id, 'string', false);
        }

        return array_values($fields);
    }
}
