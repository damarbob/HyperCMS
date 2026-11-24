<?php

use CodeIgniter\Test\CIUnitTestCase;

require_once ROOTPATH . 'tools/PackageRules.php';

/**
 * Which tracked files go into the release package.
 *
 * @internal
 */
final class PackageRulesTest extends CIUnitTestCase
{
    /**
     * @dataProvider shipped
     */
    public function testWhatASiteNeedsShips(string $path): void
    {
        $this->assertTrue(PackageRules::includes($path), $path);
    }

    /**
     * @dataProvider kept
     */
    public function testWhatASiteDoesNotNeedStaysOut(string $path): void
    {
        $this->assertFalse(PackageRules::includes($path), $path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function shipped(): iterable
    {
        foreach (['app/Config/Hyper.php', 'modules/Media/Module.php', 'public/index.php', 'public/.htaccess', 'writable/uploads/index.html', 'writable/.htaccess', 'spark', 'env', 'composer.json', 'composer.lock', 'CHANGELOG.md', 'LICENSE'] as $path) {
            yield $path => [$path];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function kept(): iterable
    {
        foreach (['tests/unit/FilesTest.php', 'tools/build.php', '.agent/rules/core.md', '.docker/entrypoint.sh', 'Dockerfile', 'docker-compose.yml', 'package.json', 'package-lock.json', 'phpunit.xml.dist', 'CLAUDE.md', '.gitignore', '.env', '.env-ori', '.env.bak', '','tests\\regression\\check.ps1'] as $path) {
            yield $path === '' ? '(empty)' : $path => [$path];
        }
    }
}
