<?php

namespace App\Commands;

use App\Data\Entries\EntryWriter;
use App\Data\Entries\FilterableFields;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Writes the clean value of every filterable field back into the entries that existed before the field was
 * marked filterable.
 *
 * A save already cleans these values, but an entry nobody has saved since still holds, for example, a date written
 * as `2025-03-20T10:30`, which StarDust cannot index. After this command the index holds a value for it too.
 */
class Normalize extends BaseCommand
{
    protected $group       = 'Hyper';
    protected $name        = 'hyper:normalize';
    protected $description = 'Cleans the stored values of filterable fields in existing entries, so the index holds them.';

    protected $usage     = 'hyper:normalize [model_id]';
    protected $arguments = [
        'model_id' => 'The model to clean (defaults to every model that has a filterable field)',
    ];

    public function run(array $params)
    {
        $db = db_connect();

        $models = [];
        foreach ($db->table('hyper_models')->select('id, name, fields')->where('deleted_at', null)->orderBy('id')->get()->getResultArray() as $row) {
            if (isset($params[0]) && (int) $params[0] !== (int) $row['id']) {
                continue;
            }
            if (FilterableFields::read($row['fields'])['fields'] !== []) {
                $models[(int) $row['id']] = $row['name'];
            }
        }

        if ($models === []) {
            CLI::write('No model has a filterable field' . (isset($params[0]) ? ' with that id.' : '.'));

            return EXIT_SUCCESS;
        }

        $failed = false;
        foreach ($models as $id => $name) {
            try {
                $report = (new EntryWriter())->normaliseModel($id);
            } catch (Throwable $e) {
                CLI::error("Model $id ($name) failed. " . $e->getMessage());
                $failed = true;

                continue;
            }

            CLI::write(sprintf('Model %d (%s) checked %d entry(ies) and cleaned %d.', $id, $name, $report['checked'], $report['rewritten']));
            foreach ($report['failed'] as $entryId => $reason) {
                CLI::error("Entry $entryId was left as it is. $reason");
                $failed = true;
            }
        }

        return $failed ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
