<?php

use App\Data\ModelStore;
use App\Data\Permissions;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The permission rules the admin has today, asked through `Permissions::can()`.
 *
 * @internal
 */
final class PermissionsTest extends CIUnitTestCase
{
    /**
     * @param array<int, list<string>> $groups Each model's `user_groups` by model id.
     */
    private function permissions(array $groups = []): Permissions
    {
        $models = $this->createMock(ModelStore::class);
        $models->method('userGroups')->willReturn($groups);

        return new Permissions($models);
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

            public function inGroup(string ...$groups): bool
            {
                return array_intersect(array_map('strtolower', $groups), $this->memberOf) !== [];
            }
        };
    }

    public function testAVisitorMayDoNothing(): void
    {
        $permissions = $this->permissions();

        foreach (Permissions::GLOBAL as $permission) {
            $this->assertFalse($permissions->can(null, $permission), $permission);
        }
        foreach (Permissions::MODEL as $permission) {
            $this->assertFalse($permissions->can(null, $permission, 1), $permission);
        }
    }

    public function testOnlyASuperadminMayManageModelsSettingsUsersAndPurge(): void
    {
        $permissions = $this->permissions();

        foreach (['models.manage', 'settings.manage', 'users.manage', 'entries.purge'] as $permission) {
            $this->assertTrue($permissions->can($this->user(['superadmin']), $permission), $permission);
            foreach (['admin', 'developer', 'beta', 'user'] as $group) {
                $this->assertFalse($permissions->can($this->user([$group]), $permission), "$group $permission");
            }
        }
    }

    public function testEveryGroupExceptUserMayManageMedia(): void
    {
        $permissions = $this->permissions();

        foreach (['superadmin', 'admin', 'developer', 'beta'] as $group) {
            $this->assertTrue($permissions->can($this->user([$group]), 'media.manage'), $group);
        }
        $this->assertFalse($permissions->can($this->user(['user']), 'media.manage'));
        $this->assertFalse($permissions->can($this->user(['user', 'beta']), 'media.manage'), 'A member of user is refused even in another group.');
    }

    public function testAModelWithNoGroupsIsOpenToEverySignedInUser(): void
    {
        $permissions = $this->permissions([1 => []]);

        foreach (['superadmin', 'admin', 'developer', 'beta', 'user'] as $group) {
            $this->assertTrue($permissions->can($this->user([$group]), 'entries.read', 1), $group);
            $this->assertTrue($permissions->can($this->user([$group]), 'entries.history', 1), $group);
        }
    }

    public function testOnlyTheWriterGroupsMayCreateUpdateAndDelete(): void
    {
        $permissions = $this->permissions([1 => []]);

        foreach (['entries.create', 'entries.update', 'entries.delete'] as $permission) {
            foreach (['superadmin', 'admin', 'developer'] as $group) {
                $this->assertTrue($permissions->can($this->user([$group]), $permission, 1), "$group $permission");
            }
            foreach (['beta', 'user'] as $group) {
                $this->assertFalse($permissions->can($this->user([$group]), $permission, 1), "$group $permission");
            }
        }
    }

    public function testAModelWithGroupsIsOpenOnlyToThoseGroups(): void
    {
        $permissions = $this->permissions([5 => ['developer']]);

        foreach (Permissions::MODEL as $permission) {
            $this->assertTrue($permissions->can($this->user(['developer']), $permission, 5), "developer $permission");
            $this->assertFalse($permissions->can($this->user(['admin']), $permission, 5), "admin $permission");
            $this->assertFalse($permissions->can($this->user(['user']), $permission, 5), "user $permission");
        }
        $this->assertFalse($permissions->can($this->user(['superadmin']), 'entries.read', 5), 'A superadmin is not let in by the group rule today.');
        $this->assertTrue($permissions->can($this->user(['admin', 'developer']), 'entries.read', 5), 'Any one of the groups is enough.');
        $this->assertFalse($permissions->can($this->user(['admin']), 'entries.read', '5'), 'The id may be text.');
    }

    public function testAnIdThatNamesNoModelLetsASignedInUserInForTheCallerToAnswer(): void
    {
        $this->assertTrue($this->permissions()->can($this->user(['user']), 'entries.read', 99));
    }

    public function testAnUnknownPermissionOrAMissingModelIdFailsLoudly(): void
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
}
