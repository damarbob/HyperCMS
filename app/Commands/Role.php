<?php

namespace App\Commands;

use App\Data\Abilities;
use App\Data\Permissions;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Lists roles and gives or takes what a role may do, from the command line. It is the way to repair access when the
 * screens cannot be reached, for example `php spark hyper:role grant admin roles.manage`.
 *
 * The superadmin role has everything and is not changed here.
 */
class Role extends BaseCommand
{
    protected $group       = 'Hyper';
    protected $name        = 'hyper:role';
    protected $description = 'List roles, and give or take what a role may do.';
    protected $usage       = 'hyper:role <action> [arguments] [options]';
    protected $arguments   = [
        'action' => 'list | abilities | show <role> | create <name> <label> | delete <name> | grant <role> <ability> | revoke <role> <ability>',
    ];
    protected $options = [
        '--model' => 'grant and revoke: the id of the model, for an action on one model such as entries.update.',
    ];

    public function run(array $params)
    {
        $action = $params[0] ?? '';

        try {
            return match ($action) {
                'list'      => $this->list(),
                'abilities' => $this->abilities(),
                'show'      => $this->show($params[1] ?? ''),
                'create'    => $this->create($params[1] ?? '', $params[2] ?? ''),
                'delete'    => $this->delete($params[1] ?? ''),
                'grant'     => $this->change($params[1] ?? '', $params[2] ?? '', true),
                'revoke'    => $this->change($params[1] ?? '', $params[2] ?? '', false),
                default     => $this->help(),
            };
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
    }

    private function help(): int
    {
        CLI::write('Usage: php spark hyper:role <action> [arguments] [options]', 'yellow');
        foreach (explode(' | ', $this->arguments['action']) as $line) {
            CLI::write('  ' . $line);
        }

        return EXIT_ERROR;
    }

    private function list(): int
    {
        $rows = [];
        foreach (service('roles')->all() as $role) {
            $rows[] = [$role['name'], $role['label'], $role['locked'] ? 'locked' : '', (string) $role['people'], $role['locked'] ? 'all' : (string) $role['abilities']];
        }
        CLI::table($rows, ['Name', 'Label', '', 'People', 'Abilities']);

        return EXIT_SUCCESS;
    }

    private function abilities(): int
    {
        $rows = [];
        foreach (service('abilities')->all() as $ability) {
            $rows[] = [$ability['name'], $ability['owner'], $ability['label']];
        }
        foreach (Abilities::MODEL_ACTIONS as $action) {
            $rows[] = ['entries.' . $action, 'core', 'On one model, give --model <id>'];
        }
        CLI::table($rows, ['Ability', 'Source', 'Label']);

        return EXIT_SUCCESS;
    }

    private function show(string $name): int
    {
        $role = service('roles')->find($name) ?? throw new \InvalidArgumentException("There is no role '$name'.");

        CLI::write("{$role['label']} ({$role['name']}), {$role['people']} people");
        if ($role['locked']) {
            CLI::write('Locked. It has every ability and every action on every model.');

            return EXIT_SUCCESS;
        }

        CLI::write('Abilities: ' . (implode(', ', service('roles')->abilitiesOf($name)) ?: 'none'));
        foreach (service('roles')->modelActionsOf($name) as $modelId => $actions) {
            CLI::write("  model $modelId: " . implode(', ', $actions));
        }

        return EXIT_SUCCESS;
    }

    private function create(string $name, string $label): int
    {
        service('roles')->create($name, $label === '' ? $name : $label, '');
        CLI::write("The role '$name' is made. Give it abilities with php spark hyper:role grant $name <ability>.", 'green');

        return EXIT_SUCCESS;
    }

    private function delete(string $name): int
    {
        service('roles')->delete($name);
        CLI::write("The role '$name' is deleted.", 'green');

        return EXIT_SUCCESS;
    }

    private function change(string $role, string $ability, bool $give): int
    {
        if ($role === '' || $ability === '') {
            CLI::error('Usage: php spark hyper:role grant|revoke <role> <ability> [--model <id>]');

            return EXIT_ERROR;
        }
        if ($role === Permissions::SUPERADMIN) {
            CLI::error('The superadmin role has everything and cannot be changed.');

            return EXIT_ERROR;
        }
        if (service('roles')->find($role) === null) {
            throw new \InvalidArgumentException("There is no role '$role'.");
        }

        $roles = service('roles');

        if (Abilities::isModelAbility($ability)) {
            $modelId = CLI::getOption('model');
            if (! is_string($modelId) || ! ctype_digit($modelId) || ! in_array((int) $modelId, service('modelStore')->allIds(), true)) {
                throw new \InvalidArgumentException('Name an existing model with --model <id>.');
            }

            $actions = $roles->modelActionsOf($role);
            $action  = substr($ability, 8);
            $mine    = $actions[(int) $modelId] ?? [];
            $mine    = $give ? array_values(array_unique(array_merge($mine, [$action]))) : array_values(array_diff($mine, [$action]));
            $actions[(int) $modelId] = $mine;
            $roles->setModelActions($role, $actions, service('modelStore')->allIds());
        } else {
            if (! service('abilities')->exists($ability)) {
                throw new \InvalidArgumentException("There is no ability '$ability'. See php spark hyper:role abilities.");
            }

            $have = $roles->abilitiesOf($role);
            $have = $give ? array_values(array_unique(array_merge($have, [$ability]))) : array_values(array_diff($have, [$ability]));
            $roles->setAbilities($role, $have, array_keys(service('abilities')->all()));
        }

        CLI::write(($give ? 'Gave ' : 'Took ') . "$ability " . ($give ? 'to ' : 'from ') . "the role $role.", 'green');

        return EXIT_SUCCESS;
    }
}
