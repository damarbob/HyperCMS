<?php

use App\Libraries\Install\EnvFile;
use CodeIgniter\Config\DotEnv;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The `.env` the install page writes must read back, through CodeIgniter's own reader, with the values that were typed.
 *
 * @internal
 */
final class EnvFileTest extends CIUnitTestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'envtest');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /**
     * @dataProvider passwords
     */
    public function testAValueReadsBackAsTyped(string $value): void
    {
        $this->assertTrue(EnvFile::writable($value));

        file_put_contents($this->file, EnvFile::render(['roundtrip.value' => $value, 'roundtrip.other' => 'x'], ['A note.']));
        $read = (new DotEnv(dirname($this->file), basename($this->file)))->parse();

        $this->assertSame($value, $read['roundtrip.value']);
        $this->assertSame('x', $read['roundtrip.other']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function passwords(): iterable
    {
        foreach ([
            'plain',
            'with space and # hash',
            'quote"inside',
            "single'quote",
            'back\\slash',
            'ends with backslash\\',
            'two\\\\slashes',
            '\\"',
            '"\\',
            'dollar$sign and $5',
            'a=b=c',
            ' leading and trailing ',
            '',
            'p@ss:w0rd/%&?',
        ] as $value) {
            yield $value === '' ? '(empty)' : $value => [$value];
        }
    }

    /**
     * @dataProvider unwritable
     */
    public function testAValueThatCannotBeKeptIsRefused(string $value): void
    {
        $this->assertFalse(EnvFile::writable($value));
        $this->expectException(InvalidArgumentException::class);
        EnvFile::render(['roundtrip.value' => $value]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unwritable(): iterable
    {
        yield 'line break'  => ["two\nlines"];
        yield 'carriage'    => ["a\rb"];
        yield 'null byte'   => ["a\0b"];
        yield 'nested name' => ['uses ${HOME} here'];
    }

    public function testASettingNameIsChecked(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EnvFile::render(['bad name' => 'x']);
    }
}
