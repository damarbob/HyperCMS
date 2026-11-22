<?php

namespace App\Libraries\Upgrade\Steps;

use App\Libraries\Upgrade\Convert\LabelField;
use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\SchemaStore;
use CodeIgniter\Database\BaseConnection;

/**
 * Step 2. Gives each model that has no label field the one its fields suggest: the first text field named `title` or
 * `name`, else the first text field. A model with no text field stays without one and is listed.
 *
 * A model that already has a label field, from a relation that named one in step 1, is left alone, so the step can run
 * again at any time and changes nothing the second time.
 */
final class PickLabelFields implements ConversionStep
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function number(): int
    {
        return 2;
    }

    public function name(): string
    {
        return 'Pick the label field of each model';
    }

    public function run(SchemaStore $store, bool $dryRun): array
    {
        $changed  = 0;
        $problems = [];

        $rows = $this->db->table('hyper_models')->select('id, name, fields')->where('label_field', null)->orderBy('id')->get()->getResultArray();
        foreach ($rows as $row) {
            $fields = json_decode((string) $row['fields'], true);
            $label  = is_array($fields) ? LabelField::pick(array_values(array_filter($fields, 'is_array'))) : null;

            if ($label === null) {
                $problems[] = "Model {$row['name']} ({$row['id']}) has no text field to name its entries by, so lists show the entry number until one is chosen.";

                continue;
            }

            $changed++;
            if (! $dryRun) {
                $this->db->table('hyper_models')->where('id', (int) $row['id'])->update(['label_field' => $label]);
            }
        }

        return ['changed' => $changed, 'problems' => $problems];
    }
}
