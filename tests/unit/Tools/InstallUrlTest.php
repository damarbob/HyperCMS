<?php

use App\Libraries\Install\InstallUrl;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The address of a site that has no settings yet, worked out from the request.
 *
 * @internal
 */
final class InstallUrlTest extends CIUnitTestCase
{
    public function testASiteAtTheRoot(): void
    {
        $this->assertSame(
            ['baseURL' => 'https://example.com/', 'indexPage' => ''],
            InstallUrl::build('https', 'example.com', '/index.php', '/install'),
        );
    }

    public function testASiteInASubfolder(): void
    {
        $this->assertSame(
            ['baseURL' => 'https://example.com/hyper/public/', 'indexPage' => ''],
            InstallUrl::build('https', 'example.com', '/hyper/public/index.php', '/hyper/public/install'),
        );
    }

    public function testAPortIsKept(): void
    {
        $this->assertSame('http://localhost:8095/', InstallUrl::build('http', 'localhost:8095', '/index.php', '/')['baseURL']);
    }

    public function testAHostWithoutRewritingKeepsTheIndexFile(): void
    {
        $this->assertSame(
            ['baseURL' => 'http://example.com/app/', 'indexPage' => 'index.php'],
            InstallUrl::build('http', 'example.com', '/app/index.php', '/app/index.php/install'),
        );
        $this->assertSame('index.php', InstallUrl::build('http', 'example.com', '/index.php', '/index.php')['indexPage']);
    }

    public function testAWindowsSeparatorIsNormalised(): void
    {
        $this->assertSame('http://example.com/site/', InstallUrl::build('http', 'example.com', '\\site\\index.php', '/site/install')['baseURL']);
    }
}
