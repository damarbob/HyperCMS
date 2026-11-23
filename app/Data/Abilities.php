<?php

namespace App\Data;

use CodeIgniter\Database\BaseConnection;
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

    private static function text(string $name, string $suffix): string
    {
        $key  = 'Abilities.' . str_replace('.', '_', $name) . $suffix;
        $text = lang($key);

        return $text === $key ? $name : $text;
    }
}
