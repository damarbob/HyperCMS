<?php

use App\Libraries\Install\InstallState;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * When the install page may be used. A wrong answer here either locks an owner out or lets a stranger set up the site.
 *
 * @internal
 */
final class InstallStateTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hyper_install_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testAnUnconfiguredSiteIsOpen(): void
    {
        $this->assertTrue((new InstallState($this->dir, false))->open());
    }

    public function testAConfiguredSiteWithNoInstallUnderWayIsLocked(): void
    {
        // A site that was set up from the command line, or before this page existed.
        $this->assertFalse((new InstallState($this->dir, true))->open());
    }

    public function testASiteWhoseInstallIsUnderWayStaysOpenOnceItsDatabaseIsConfigured(): void
    {
        $state = new InstallState($this->dir, false);
        $state->key();

        $this->assertTrue((new InstallState($this->dir, true))->open());
    }

    public function testTheMarkerLocksTheSiteWhateverElseIsTrue(): void
    {
        $state = new InstallState($this->dir, false);
        $state->key();
        $state->finish();

        $this->assertTrue($state->installed());
        $this->assertFalse($state->open());
        $this->assertFalse((new InstallState($this->dir, true))->open());
        $this->assertFalse((new InstallState($this->dir, false))->open(), 'a database that is down must not reopen the page');
    }

    public function testFinishingRemovesTheKeyAndTheAnswers(): void
    {
        $state = new InstallState($this->dir, false);
        $state->key();
        $state->saveAnswers(['admin' => ['username' => 'owner']]);
        $this->assertSame(['admin' => ['username' => 'owner']], $state->answers());

        $state->finish();

        $this->assertNull($state->answers());
        $this->assertFileDoesNotExist($state->keyPath());
    }

    public function testTheKeyIsMadeOnceAndChecked(): void
    {
        $state = new InstallState($this->dir, false);
        $key   = $state->key();

        $this->assertSame(32, strlen($key));
        $this->assertSame($key, (new InstallState($this->dir, false))->key());
        $this->assertTrue($state->keyMatches($key));
        $this->assertTrue($state->keyMatches("  $key\n"));
        $this->assertFalse($state->keyMatches($key . 'x'));
        $this->assertFalse($state->keyMatches(''));
        $this->assertFalse($state->keyMatches(null));
    }

    public function testNoKeyFileMeansNoKeyMatches(): void
    {
        // A key that was never made must not be guessable by asking for the empty one.
        $this->assertFalse((new InstallState($this->dir, false))->keyMatches('anything'));
    }
}
