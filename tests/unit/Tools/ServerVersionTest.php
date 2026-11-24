<?php

use App\Libraries\Install\ServerVersion;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Which database servers the install page accepts.
 *
 * @internal
 */
final class ServerVersionTest extends CIUnitTestCase
{
    /**
     * @dataProvider reports
     */
    public function testWhichVersionsAreSupported(string $reported, bool $supported, string $label): void
    {
        $this->assertSame($supported, ServerVersion::supported($reported), $reported);
        $this->assertSame($label, ServerVersion::label($reported));
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function reports(): iterable
    {
        yield 'mysql at the floor'       => ['8.0.13', true, 'MySQL 8.0.13'];
        yield 'mysql below the floor'    => ['8.0.12', false, 'MySQL 8.0.12'];
        yield 'mysql 5.7'                => ['5.7.44-log', false, 'MySQL 5.7.44'];
        yield 'mysql 8.4'                => ['8.4.3', true, 'MySQL 8.4.3'];
        yield 'percona'                  => ['8.0.36-28', true, 'MySQL 8.0.36'];
        yield 'mariadb at the floor'     => ['10.11.0-MariaDB', true, 'MariaDB 10.11.0'];
        yield 'mariadb below the floor'  => ['10.6.18-MariaDB-0ubuntu0.22.04.1', false, 'MariaDB 10.6.18'];
        yield 'mariadb 11'               => ['11.4.2-MariaDB', true, 'MariaDB 11.4.2'];
        yield 'mariadb old prefix'       => ['5.5.5-10.11.6-MariaDB-log', true, 'MariaDB 10.11.6'];
        yield 'not a version'            => ['', false, 'MySQL 0.0.0'];
    }
}
