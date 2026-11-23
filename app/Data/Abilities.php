<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use Throwable;

/**
 * The abilities of the admin: what a role can be allowed to do.
 *
 * An ability has a name such as `media.manage`, a label and a description for the screens, and an owner. Core and the
 * modules register theirs in code, and an admin can add custom ones, which only matter to code that checks their name.
 *
 * Besides these there are the actions on one model (`entries.read` and its four siblings), which are granted per model
 * and not per role.
 */
class Abilities
{
    /**
     * The abilities of core, which need no model.
     */
    public const CORE = ['models.manage', 'settings.manage', 'users.manage', 'roles.manage', 'media.manage', 'pages.manage', 'tokens.manage', 'entries.browse', 'entries.purge', 'api.access'];

    /**
     * The actions a role can be given on one model, as the part after `entries.`.
     */
    public const MODEL_ACTIONS = ['read', 'create', 'update', 'delete', 'history'];

    /**
     * The shape of a name an admin can give a custom ability: lowercase words joined by dots, with at least two.
     */
    public const NAME = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    /**
     * Abilities that modules registered, by name.
     *
     * @var array<string, array{name: string, label: string, description: string, owner: string}>
     */
    private array $registered = [];

    /**
     * @var array<string, array{name: string, label: string, description: string}>|null
     */
    private ?array $custom = null;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Add an ability of a module. A module calls this when it loads, and the name must start with its own area.
     */
    public function register(string $name, string $label, string $description, string $owner): void
    {
        $this->registered[$name] = ['name' => $name, 'label' => $label, 'description' => $description, 'owner' => $owner];
    }

    /**
     * Every ability that needs no model, by name, with where it came from.
     *
     * @return array<string, array{name: string, label: string, description: string, owner: string, custom: bool}>
     */
    public function all(): array
    {
        $all = [];
        foreach (self::CORE as $name) {
            $all[$name] = ['name' => $name, 'label' => self::text($name, ''), 'description' => self::text($name, '_text'), 'owner' => 'core', 'custom' => false];
        }
        foreach ($this->registered as $name => $ability) {
            $all[$name] = $ability + ['custom' => false];
        }
        foreach ($this->custom() as $name => $ability) {
            $all[$name] ??= $ability + ['owner' => 'custom', 'custom' => true];
        }

        return $all;
    }

    public function exists(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    /**
     * Whether a name is one of the actions on a model, such as `entries.update`.
     */
    public static function isModelAbility(string $name): bool
    {
        return str_starts_with($name, 'entries.') && in_array(substr($name, 8), self::MODEL_ACTIONS, true);
    }

    /**
     * The abilities an admin made.
     *
     * @return array<string, array{name: string, label: string, description: string}>
     */
    public function custom(): array
    {
        if ($this->custom === null) {
            $this->custom = [];
            try {
                foreach ($this->db->table('hyper_abilities')->orderBy('name')->get()->getResultArray() as $row) {
                    $this->custom[(string) $row['name']] = ['name' => (string) $row['name'], 'label' => (string) $row['label'], 'description' => (string) ($row['description'] ?? '')];
                }
            } catch (Throwable) {
                // The table does not exist before the migration, and there are no custom abilities then.
            }
        }

        return $this->custom;
    }

    public function forget(): void
    {
        $this->custom = null;
    }

    /**
     * Add an ability by name. It only matters to code that checks that name, such as a module or a template.
     *
     * @throws InvalidArgumentException When the name is not a name or is taken, or the label is empty. The message is for the person.
     */
    public function createCustom(string $name, string $label, string $description): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException(lang('Roles.abilityNameInvalid'));
        }
        if ($this->exists($name) || self::isModelAbility($name)) {
            throw new InvalidArgumentException(lang('Roles.abilityNameTaken', ['name' => $name]));
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException(lang('Roles.labelRequired'));
        }

        $this->db->table('hyper_abilities')->insert(['name' => $name, 'label' => trim($label), 'description' => trim($description) === '' ? null : trim($description), 'created_at' => date('Y-m-d H:i:s')]);
        $this->forget();
    }

    /**
     * Remove an ability an admin made, and take it from every role. An ability of core or a module cannot be removed.
     *
     * @throws InvalidArgumentException When the ability is not a custom one.
     */
    public function deleteCustom(string $name): void
    {
        if (! isset($this->custom()[$name]) || isset($this->registered[$name]) || in_array($name, self::CORE, true)) {
            throw new InvalidArgumentException(lang('Roles.abilityNotCustom'));
        }

        $this->db->transStart();
        $this->db->table('hyper_role_abilities')->where('ability', $name)->delete();
        $this->db->table('hyper_abilities')->where('name', $name)->delete();
        $this->db->transComplete();
        $this->forget();
    }

    /**
     * The roles that have each ability, by ability name.
     *
     * @return array<string, list<string>>
     */
    public function holders(): array
    {
        $by = [];
        foreach ($this->db->table('hyper_role_abilities')->orderBy('role')->get()->getResultArray() as $row) {
            $by[(string) $row['ability']][] = (string) $row['role'];
        }

        return $by;
    }

    private static function text(string $name, string $suffix): string
    {
        $key  = 'Abilities.' . str_replace('.', '_', $name) . $suffix;
        $text = lang($key);

        return $text === $key ? $name : $text;
    }
}
