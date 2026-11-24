<?php

namespace App\Libraries\Upgrade\Steps;

use App\Data\ModelGrants;
use App\Libraries\Upgrade\ConversionStep;
use App\Libraries\Upgrade\SchemaStore;
use CodeIgniter\Database\BaseConnection;

/**
 * Step 4. Turns the `user_groups` of every model, the groups that may open it, into rows of `hyper_model_permissions`.
 *
 * The roles and what they may do besides that are added by the migration `CreateRolesAndAbilities`, so this step only
 * has to read each model once. A model that already has rows, because the builder wrote them or an admin changed them,
 * is left alone, so the step can run again at any time and changes nothing the second time. A trashed model is
 * converted too, because it comes back with its permissions.
 */
final class ConvertPermissions implements ConversionStep
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function number(): int
    {
        return 4;
    }

    public function name(): string
    {
        return 'Turn the user groups of each model into permissions';
    }

    public function run(SchemaStore $store, bool $dryRun, ?float $deadline = null): array
    {
        $roles    = array_column($this->db->table('hyper_roles')->select('name')->get()->getResultArray(), 'name');
        $cursor   = $store->cursor($this->number()) ?? 0;
        $changed  = 0;
        $problems = [];

        $rows = $this->db->table('hyper_models')->select('id, name, user_groups')->where('id >', $cursor)->orderBy('id')->get()->getResultArray();
        foreach ($rows as $row) {
            $id = (int) $row['id'];

            if ($this->db->table('hyper_model_permissions')->where('model_id', $id)->countAllResults() === 0) {
                $groups = json_decode((string) ($row['user_groups'] ?? '[]'), true);
                $groups = is_array($groups) ? array_values(array_filter($groups, 'is_string')) : [];

                foreach (array_diff($groups, $roles) as $unknown) {
                    $problems[] = "Model {$row['name']} ($id) names the group '$unknown', which is not a role, so it gets nothing from it.";
                }

                $changed++;
                if (! $dryRun) {
                    $this->db->transStart();
                    // A list that names no role at all must not read as an empty list, which means every staff role.
                    $valid  = array_values(array_intersect($groups, $roles));
                    $grants = $groups !== [] && $valid === [] ? [] : ModelGrants::fromGroups($valid, $roles);
                    foreach ($grants as $grant) {
                        $this->db->table('hyper_model_permissions')->insert(['model_id' => $id, 'role' => $grant['role'], 'action' => $grant['action']]);
                    }
                    $this->db->transComplete();
                }
            }

            if (! $dryRun) {
                $store->setCursor($this->number(), $id);
            }
        }

        return ['changed' => $changed, 'problems' => $problems];
    }
}
