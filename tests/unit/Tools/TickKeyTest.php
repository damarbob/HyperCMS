<?php

use App\Libraries\TickKey;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * When the tick address exists and which key it accepts.
 *
 * @internal
 */
final class TickKeyTest extends CIUnitTestCase
{
    private const SECRET = 'a-secret-of-at-least-thirty-two-characters';

    public function testNoSecretMeansNoAddress(): void
    {
        $this->assertFalse(TickKey::enabled(''));
        $this->assertFalse(TickKey::enabled(str_repeat('a', TickKey::MIN_LENGTH - 1)));
        $this->assertTrue(TickKey::enabled(str_repeat('a', TickKey::MIN_LENGTH)));
    }

    public function testTheRightKeyIsAccepted(): void
    {
        $this->assertTrue(TickKey::matches(self::SECRET, self::SECRET));
    }

    public function testAWrongOrMissingKeyIsRefused(): void
    {
        $this->assertFalse(TickKey::matches(self::SECRET, self::SECRET . 'x'));
        $this->assertFalse(TickKey::matches(self::SECRET, substr(self::SECRET, 1)));
        $this->assertFalse(TickKey::matches(self::SECRET, ''));
        $this->assertFalse(TickKey::matches(self::SECRET, null));
    }

    public function testASecretThatIsTooShortNeverMatches(): void
    {
        $this->assertFalse(TickKey::matches('short', 'short'));
        $this->assertFalse(TickKey::matches('', ''));
    }
}
