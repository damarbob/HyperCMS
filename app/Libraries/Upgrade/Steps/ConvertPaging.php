<?php

namespace App\Libraries\Upgrade\Steps;

use App\Data\Entries\EntryEngine;
use App\Data\IndexingFailed;
use App\Data\ModelStore;
use App\Data\WriteVersions;
use App\Libraries\Upgrade\Convert\PageUrls;
use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\Payload;
use App\Libraries\Upgrade\SchemaStore;
use App\Modules\Blueprints;
use App\Modules\Modules;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Settings\Settings;

/**
 * Step 6. Moves the page module to the models it was told to use and the lookup by address.
 *
 * The page, asset and meta models that the module used are kept in the settings of the module, as the model that was chosen
 * before when it still fits, or else the first model that has the fields. The two fields that a visitor's request is
 * answered from, the address and the attachment of a page, are made filterable so the engine indexes them, and a main page
 * that has a title and no address gets the address its title made, which is how the module used to find it.
 *
 * Nothing here changes what a page looks like. A model that fits nothing is listed and left for the settings of the module.
 */
final class ConvertPaging implements ConversionStep
{
    private const OLD_SETTINGS = ['page' => 'PagingSystem.primaryModelId', 'asset' => 'PagingSystem.assetsModelId', 'meta' => 'PagingSystem.metaModelId'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly ModelStore $models,
        private readonly Modules $modules,
        private readonly Settings $settings,
        private readonly EntryEngine $engine,
        private readonly WriteVersions $versions,
    ) {
    }

    public function number(): int
    {
        return 6;
    }

    public function name(): string
    {
        return 'Choose the models of the page module and index its addresses';
    }

    public function run(SchemaStore $store, bool $dryRun): array
    {
        if (! isset($this->modules->all()['PagingSystem'])) {
            return ['changed' => 0, 'problems' => ['The page module is not active, so its models were not chosen. Choose them in its settings when it is.']];
        }

        $changed  = 0;
        $problems = [];
        $blueprints = new Blueprints($this->models, $this->modules);
        $chosen   = [];

        foreach (self::OLD_SETTINGS as $key => $old) {
            $fitting = array_column($blueprints->fitting($blueprints->find('PagingSystem', $key)), 'id');
            $current = $this->modules->modelOf('PagingSystem', $key);
            if ($current !== null && in_array($current, $fitting, true)) {
                $chosen[$key] = $current;

                continue;
            }

            $was  = (int) $this->settings->get($old);
            $pick = in_array($was, $fitting, true) ? $was : ($fitting[0] ?? null);
            if ($pick === null) {
                $problems[] = "No model has the fields of the $key model of the page module, so none is chosen. Make one in the settings of the module.";

                continue;
            }

            $chosen[$key] = $pick;
            $changed++;
            if (! $dryRun) {
                $this->modules->useModel('PagingSystem', $key, $pick);
            }
        }

        if (isset($chosen['page'])) {
            $changed += $this->indexAddresses($chosen['page'], $dryRun, $problems);
            $changed += $this->fillAddresses($chosen['page'], $dryRun);
        }

        return ['changed' => $changed, 'problems' => $problems];
    }

    /**
     * Make the address and the attachment of the page model filterable.
     *
     * @param list<string> $problems
     */
    private function indexAddresses(int $modelId, bool $dryRun, array &$problems): int
    {
        $row = $this->models->findArray($modelId);
        if (! is_array($row)) {
            return 0;
        }

        $fields = json_decode((string) $row['fields'], true);
        if (! is_array($fields)) {
            return 0;
        }

        $touched = false;
        foreach ($fields as $at => $field) {
            if (is_array($field) && in_array($field['id'] ?? null, ['hyper_page_url', 'hyper_page_hook_id'], true) && empty($field['filterable'])) {
                $fields[$at]['filterable'] = true;
                $touched                   = true;
            }
        }
        if (! $touched) {
            return 0;
        }

        if (! $dryRun) {
            try {
                $this->models->update($modelId, [
                    'name'        => (string) $row['name'],
                    'group'       => $row['group'] ?? null,
                    'icon'        => $row['icon'] ?? null,
                    'user_groups' => $row['user_groups'] ?? null,
                    'label_field' => $row['label_field'] ?? null,
                    'fields'      => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], (int) $this->db->table('hyper_models')->select('creator_id')->where('id', $modelId)->get()->getRow('creator_id'));
            } catch (IndexingFailed $e) {
                // The model is saved. Indexing starts when the tick can, and pages are found by reading entries until then.
                $problems[] = 'The model was saved but indexing its addresses could not start yet: ' . $e->getMessage();
            }
        }

        return 1;
    }

    /**
     * Write the address a title makes into each main page that has none.
     */
    private function fillAddresses(int $modelId, bool $dryRun): int
    {
        $changed = 0;
        $rows    = $this->db->query('SELECT id, sd_entry_id FROM ' . $this->db->prefixTable('hyper_entries') . ' WHERE model_id = ? AND deleted_at IS NULL AND sd_entry_id IS NOT NULL', [$modelId])->getResultArray();

        foreach ($rows as $row) {
            $live = $this->engine->get((int) $row['sd_entry_id']);
            if ($live === null) {
                continue;
            }

            $filled = PageUrls::fill($live);
            if (json_encode($filled) === json_encode($live)) {
                continue;
            }

            $changed++;
            if (! $dryRun) {
                $this->engine->update((int) $row['sd_entry_id'], $filled);
                $this->db->table('hyper_entries')->where('id', (int) $row['id'])->update(['payload_bytes' => strlen(Payload::encode((object) $filled))]);
            }
        }

        if (! $dryRun && $changed > 0) {
            $this->versions->bump([$modelId]);
        }

        return $changed;
    }
}
