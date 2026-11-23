<?php

use App\Data\Abilities;
use App\Data\GrantSource;
use App\Data\ModelGrants;
use App\Data\Permissions;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * What `Permissions::can()` answers from the roles of a user and what each role may do. The grants here are the ones the
 * migration seeds, so they also show that nobody's access changed.
 *
 * @internal
 */
final class PermissionsTest extends CIUnitTestCase
{
    private const ROLES = ['superadmin', 'admin', 'developer', 'user', 'beta'];

    /**
     * The global abilities of each role as the migration seeds them.
     */
    private const SEEDED = [
        'admin'     => ['entries.browse', 'api.access', 'media.manage'],
        'developer' => ['entries.browse', 'api.access', 'media.manage', 'pages.manage'],
        'beta'      => ['media.manage'],
    ];

    /**
     * @param array<int, list<string>>   $models    The `user_groups` of each model by id, turned into permissions as the upgrade does.
     * @param array<string, list<string>> $granted  Global abilities by role, as the table holds them.
     * @param list<string>                $known    Abilities that exist besides those of core.
     */
    private function permissions(array $models = [], array $granted = self::SEEDED, array $known = []): Permissions
    {
        $byModel = [];
        foreach ($models as $id => $groups) {
            foreach (ModelGrants::fromGroups($groups, self::ROLES) as $row) {
                $byModel[$id][$row['action']][] = $row['role'];
            }
        }

        return new Permissions(new class ($byModel, $granted, array_merge(Abilities::CORE, $known)) implements GrantSource {
            public function __construct(private array $byModel, private array $granted, private array $known)
            {
            }

            public function known(string $ability): bool
            {
                return Abilities::isModelAbility($ability) || in_array($ability, $this->known, true);
            }

            public function rolesWithAbility(string $ability): array
            {
                $roles = [];
                foreach ($this->granted as $role => $abilities) {
                    if (in_array($ability, $abilities, true)) {
                        $roles[] = $role;
                    }
                }

                return $roles;
            }

            public function rolesWithModelAction(int $modelId, string $action): array
            {
                return $this->byModel[$modelId][$action] ?? [];
            }

            public function abilitiesOfRole(string $role): array
            {
                return $this->granted[$role] ?? [];
            }

            public function modelActionsOfRole(string $role): array
            {
                $by = [];
                foreach ($this->byModel as $modelId => $actions) {
                    foreach ($actions as $action => $roles) {
                        if (in_array($role, $roles, true)) {
                            $by[$modelId][] = $action;
                        }
                    }
                }

                return $by;
            }
        });
    }

    /**
     * @param list<string> $groups
     */
    private function user(array $groups): User
    {
        return new class ($groups) extends User {
            /**
             * @param list<string> $groups
             */
            public function __construct(private array $memberOf)
            {
                parent::__construct();
            }

            public function getGroups(): ?array
            {
                return $this->memberOf;
            }
        };
    }

    public function testAVisitorMayDoNothing(): void
    {
        $permissions = $this->permissions();

        foreach (Abilities::CORE as $ability) {
            $this->assertFalse($permissions->can(null, $ability), $ability);
        }
        foreach (Abilities::MODEL_ACTIONS as $action) {
            $this->assertFalse($permissions->can(null, 'entries.' . $action, 1), $action);
        }
    }

    public function testASuperadminMayDoEverythingEvenWhatAnAdminAddedByHand(): void
    {
        $permissions = $this->permissions([1 => ['developer']], self::SEEDED, ['shop.refund']);
        $user        = $this->user(['superadmin']);

        foreach (array_merge(Abilities::CORE, ['shop.refund']) as $ability) {
            $this->assertTrue($permissions->can($user, $ability), $ability);
        }
        foreach (Abilities::MODEL_ACTIONS as $action) {
            $this->assertTrue($permissions->can($user, 'entries.' . $action, 1), $action);
            $this->assertTrue($permissions->can($user, 'entries.' . $action, 99), 'Even a model with no row.');
        }
    }

    public function testOnlyASuperadminMayManageModelsSettingsUsersRolesAndPurge(): void
    {
        $permissions = $this->permissions();

        foreach (['models.manage', 'settings.manage', 'users.manage', 'roles.manage', 'tokens.manage', 'entries.purge'] as $ability) {
            foreach (['admin', 'developer', 'beta', 'user'] as $role) {
                $this->assertFalse($permissions->can($this->user([$role]), $ability), "$role $ability");
            }
        }
    }

    public function testEveryRoleExceptUserMayManageMedia(): void
    {
        $permissions = $this->permissions();

        foreach (['superadmin', 'admin', 'developer', 'beta'] as $role) {
            $this->assertTrue($permissions->can($this->user([$role]), 'media.manage'), $role);
        }
        $this->assertFalse($permissions->can($this->user(['user']), 'media.manage'));
    }

    public function testTheStaffRolesBrowseEntriesAndUseTheApiAndOnlyDevelopersManagePages(): void
    {
        $permissions = $this->permissions();

        foreach (['entries.browse', 'api.access'] as $ability) {
            foreach (['superadmin', 'admin', 'developer'] as $role) {
                $this->assertTrue($permissions->can($this->user([$role]), $ability), "$role $ability");
            }
            foreach (['beta', 'user'] as $role) {
                $this->assertFalse($permissions->can($this->user([$role]), $ability), "$role $ability");
            }
        }
        $this->assertTrue($permissions->can($this->user(['developer']), 'pages.manage'));
        $this->assertFalse($permissions->can($this->user(['admin']), 'pages.manage'));
    }

    public function testAPersonWithSeveralRolesHasWhatAnyOfThemHas(): void
    {
        $permissions = $this->permissions();

        $this->assertTrue($permissions->can($this->user(['user', 'developer']), 'pages.manage'));
        $this->assertFalse($permissions->can($this->user(['user', 'beta']), 'pages.manage'));
    }

    public function testAnAbilityAnAdminGrantedToARoleIsAllowedForThatRoleOnly(): void
    {
        $permissions = $this->permissions([], self::SEEDED + ['user' => ['shop.refund']], ['shop.refund']);

        $this->assertTrue($permissions->can($this->user(['user']), 'shop.refund'));
        $this->assertFalse($permissions->can($this->user(['admin']), 'shop.refund'));
    }

    public function testAModelWithNoGroupsIsOpenToEveryRoleExceptUser(): void
    {
        $permissions = $this->permissions([1 => []]);

        foreach (['admin', 'developer', 'beta'] as $role) {
            $this->assertTrue($permissions->can($this->user([$role]), 'entries.read', 1), $role);
            $this->assertTrue($permissions->can($this->user([$role]), 'entries.history', 1), $role);
        }
        $this->assertFalse($permissions->can($this->user(['user']), 'entries.read', 1));
    }

    public function testOnlyTheWriterRolesMayCreateUpdateAndDelete(): void
    {
        $permissions = $this->permissions([1 => []]);

        foreach (['entries.create', 'entries.update', 'entries.delete'] as $ability) {
            foreach (['admin', 'developer'] as $role) {
                $this->assertTrue($permissions->can($this->user([$role]), $ability, 1), "$role $ability");
            }
            foreach (['beta', 'user'] as $role) {
                $this->assertFalse($permissions->can($this->user([$role]), $ability, 1), "$role $ability");
            }
        }
    }

    public function testAModelWithGroupsIsOpenOnlyToThoseRoles(): void
    {
        $permissions = $this->permissions([5 => ['developer']]);

        foreach (Abilities::MODEL_ACTIONS as $action) {
            $this->assertTrue($permissions->can($this->user(['developer']), 'entries.' . $action, 5), "developer $action");
            $this->assertFalse($permissions->can($this->user(['admin']), 'entries.' . $action, 5), "admin $action");
            $this->assertFalse($permissions->can($this->user(['user']), 'entries.' . $action, 5), "user $action");
        }
        $this->assertTrue($permissions->can($this->user(['admin', 'developer']), 'entries.read', 5), 'Any one of the roles is enough.');
        $this->assertTrue($permissions->can($this->user(['developer']), 'entries.read', '5'), 'The id may be text.');
    }

    public function testAListedRoleThatCouldNotWriteBeforeStillCannot(): void
    {
        $permissions = $this->permissions([7 => ['beta']]);

        $this->assertTrue($permissions->can($this->user(['beta']), 'entries.read', 7));
        $this->assertTrue($permissions->can($this->user(['beta']), 'entries.history', 7));
        $this->assertFalse($permissions->can($this->user(['beta']), 'entries.update', 7));
    }

    public function testAnIdThatNamesNoModelIsRefusedToEveryoneButASuperadmin(): void
    {
        $permissions = $this->permissions();

        $this->assertFalse($permissions->can($this->user(['admin']), 'entries.read', 99));
        $this->assertTrue($permissions->can($this->user(['superadmin']), 'entries.read', 99));
    }

    public function testAnUnknownAbilityOrAMissingModelIdFailsLoudly(): void
    {
        $permissions = $this->permissions();

        try {
            $permissions->can($this->user(['superadmin']), 'models.manag');
            $this->fail('A typo should throw.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('models.manag', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $permissions->can($this->user(['superadmin']), 'entries.update');
    }

    public function testASuperadminMayHandleAnyRoleAndNobodyElseMayHandleTheSuperadminRole(): void
    {
        $permissions = $this->permissions([1 => []]);

        foreach (self::ROLES as $role) {
            $this->assertTrue($permissions->mayAssignRole($this->user(['superadmin']), $role), $role);
        }
        $this->assertFalse($permissions->mayAssignRole($this->user(['admin']), 'superadmin'));
        $this->assertFalse($permissions->mayAssignRole(null, 'user'));
    }

    public function testSomeoneMayOnlyHandleARoleWhoseAbilitiesAndModelActionsTheyHave(): void
    {
        $permissions = $this->permissions([1 => []]);

        $this->assertTrue($permissions->mayAssignRole($this->user(['developer']), 'admin'), 'A developer has everything an admin has.');
        $this->assertFalse($permissions->mayAssignRole($this->user(['admin']), 'developer'), 'An admin lacks pages.manage.');
        $this->assertTrue($permissions->mayAssignRole($this->user(['admin']), 'user'), 'A role with nothing is safe to give.');
        $this->assertFalse($permissions->mayAssignRole($this->user(['beta']), 'admin'));
    }

    public function testAPersonMayManageAnAccountOnlyWhenTheyMayHandleEveryRoleOfIt(): void
    {
        $permissions = $this->permissions([1 => []]);

        $this->assertFalse($permissions->mayManageUser($this->user(['admin']), $this->user(['superadmin'])));
        $this->assertFalse($permissions->mayManageUser($this->user(['admin']), $this->user(['user', 'developer'])));
        $this->assertTrue($permissions->mayManageUser($this->user(['admin']), $this->user(['user'])));
        $this->assertTrue($permissions->mayManageUser($this->user(['superadmin']), $this->user(['superadmin'])));
    }
}
