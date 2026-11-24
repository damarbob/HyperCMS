<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\HyperEngine;

/**
 * CodeIgniter matches a setting in `.env` by the lowercase name of the config class, exactly, so `hyperEngine.tickSecret` is
 * ignored on Linux while Windows, which ignores the case of environment names, accepts it. The install page and the guide
 * must therefore write the lowercase form.
 *
 * @internal
 */
final class ConfigKeysTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['hyperengine.tickSecret'], $_ENV['hyperEngine.tickSecret']);
        parent::tearDown();
    }

    public function testTheLowercaseKeyReachesTheConfig(): void
    {
        $_ENV['hyperengine.tickSecret'] = str_repeat('a', 40);

        $this->assertSame(str_repeat('a', 40), (new HyperEngine())->tickSecret);
    }

    public function testTheMixedCaseKeyIsIgnoredOnAnyCaseSensitiveHost(): void
    {
        $_ENV['hyperEngine.tickSecret'] = str_repeat('a', 40);

        $this->assertSame('', (new HyperEngine())->tickSecret);
    }

    public function testNoShippedTextNamesTheMixedCaseKey(): void
    {
        foreach (['app/Libraries/Install/Installer.php', 'docs/hosting.md', 'app/Config/HyperEngine.php', '.docker/entrypoint.sh', 'env'] as $file) {
            $this->assertStringNotContainsString('hyperEngine.', (string) file_get_contents(ROOTPATH . $file), $file);
            $this->assertStringNotContainsString('hyperEngine_', (string) file_get_contents(ROOTPATH . $file), $file);
        }
    }
}
