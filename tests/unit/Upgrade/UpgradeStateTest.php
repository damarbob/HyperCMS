<?php

use App\Libraries\Upgrade\UpgradeState;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * When the site is sent to the upgrade page. A wrong answer either locks a good site behind the page or lets one run on data
 * the code cannot read.
 *
 * @internal
 */
final class UpgradeStateTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hyper_upgrade_' . bin2hex(random_bytes(4));
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

    /**
     * @dataProvider cases
     */
    public function testWhenTheDataIsBehind(bool $oldLayout, bool $finished, ?int $version, bool $expected, string $why): void
    {
        $this->assertSame($expected, UpgradeState::behind($oldLayout, $finished, $version, 6), $why);
    }

    /**
     * @return iterable<string, array{bool, bool, ?int, bool, string}>
     */
    public static function cases(): iterable
    {
        yield 'old tables, never upgraded'      => [true, false, null, true, 'the version 0.1 or 0.2 tables are all there is'];
        yield 'old tables, half copied'         => [true, false, 3, true, 'the finished mark is missing'];
        yield 'old tables left, upgrade done'   => [true, true, 6, false, 'the old tables are never dropped'];
        yield 'old tables left, conversions due' => [true, true, 4, true, 'a newer release added a step'];
        yield 'fresh install'                   => [false, false, 6, false, 'a new install starts at the latest version'];
        yield 'no old tables, version behind'   => [false, false, 2, true, 'a database from before the steps'];
        yield 'no counters table at all'        => [false, false, null, false, 'nothing to upgrade'];
    }

    public function testTheMarkerSaysTheSiteIsCurrentOnlyForTheSameVersion(): void
    {
        $state = new UpgradeState($this->dir);
        $this->assertFalse($state->known(6));

        $state->remember(6);

        $this->assertTrue($state->known(6));
        $this->assertFalse($state->known(7), 'A release with another step must look again.');
    }

    public function testTheKeyIsMadeOnceAndChecked(): void
    {
        $state = new UpgradeState($this->dir);
        $this->assertFalse($state->keyMatches('anything'), 'No key file yet.');

        $key = $state->key();

        $this->assertSame(32, strlen($key));
        $this->assertSame($key, (new UpgradeState($this->dir))->key());
        $this->assertTrue($state->keyMatches(" $key\n"));
        $this->assertFalse($state->keyMatches($key . 'x'));
        $this->assertFalse($state->keyMatches(''));
        $this->assertFalse($state->keyMatches(null));
    }

    public function testProgressRoundTripsAndFinishingClearsIt(): void
    {
        $state = new UpgradeState($this->dir);
        $this->assertNull($state->progress());

        $state->key();
        $state->saveProgress(['phase' => 'payloads', 'tally' => ['payloads' => 120]]);
        $this->assertSame('payloads', $state->progress()['phase']);

        $state->finish(6);

        $this->assertNull($state->progress());
        $this->assertFileDoesNotExist($state->keyPath());
        $this->assertTrue($state->known(6));
    }
}
