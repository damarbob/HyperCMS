<?php

use App\Data\ModelGrants;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * How the `user_groups` of a model become the actions of each role on it.
 *
 * @internal
 */
final class ModelGrantsTest extends CIUnitTestCase
{
    private const ROLES = ['superadmin', 'admin', 'developer', 'user', 'beta'];

    /**
     * @param list<array{role: string, action: string}> $rows
     *
     * @return array<string, list<string>>
     */
    private function byRole(array $rows): array
    {
        $by = [];
        foreach ($rows as $row) {
            $by[$row['role']][] = $row['action'];
        }

        return $by;
    }

    public function testAnEmptyListGivesEveryRoleExceptUserAndSuperadmin(): void
    {
        $by = $this->byRole(ModelGrants::fromGroups([], self::ROLES));

        $this->assertSame(['admin', 'developer', 'beta'], array_keys($by));
        $this->assertSame(['read', 'create', 'update', 'delete', 'history'], $by['admin']);
        $this->assertSame(['read', 'history'], $by['beta']);
    }

    public function testAListedGroupGetsReadAndHistoryAndTheWritersGetEverything(): void
    {
        $by = $this->byRole(ModelGrants::fromGroups(['developer', 'user'], self::ROLES));

        $this->assertSame(['read', 'create', 'update', 'delete', 'history'], $by['developer']);
        $this->assertSame(['read', 'history'], $by['user']);
        $this->assertArrayNotHasKey('admin', $by);
    }

    public function testASuperadminNeedsNoRowAndADuplicateCountsOnce(): void
    {
        $by = $this->byRole(ModelGrants::fromGroups(['superadmin', 'admin', 'admin'], self::ROLES));

        $this->assertSame(['admin'], array_keys($by));
        $this->assertCount(5, $by['admin']);
    }
}
