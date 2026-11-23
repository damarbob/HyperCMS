<?php

use App\Data\Files;
use App\Data\MediaException;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The rules of the media library that need no database: which names and folders are accepted, which kind a type is, and the
 * folders on the disk.
 *
 * @internal
 */
final class FilesTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hyper_files_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $this->removeTree($this->dir);
        }
        parent::tearDown();
    }

    private function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testANameThatAServerCouldRunIsRefused(): void
    {
        foreach (['x.php', 'x.php.jpg', 'x.PHTML', '.htaccess', '.env', 'a.user.ini', 'x.phar', 'photo.png.'] as $name) {
            try {
                Files::cleanName($name);
                $this->fail("$name was accepted");
            } catch (MediaException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnOrdinaryNameIsKeptAndAPathIsReducedToItsName(): void
    {
        $this->assertSame('photo 1.png', Files::cleanName('photo 1.png'));
        $this->assertSame('cat.jpg', Files::cleanName('C:\Users\me\cat.jpg'));
        $this->assertSame('x.png', Files::cleanName('../../x.png'));
        $this->assertSame('a-b.txt', Files::cleanName('a*b.txt'));
    }

    public function testAFolderIsACleanRelativePath(): void
    {
        $this->assertSame('', Files::cleanFolder(''));
        $this->assertSame('a/b', Files::cleanFolder('/a//b/'));
        $this->assertSame('a/b', Files::cleanFolder('a\b'));

        foreach (['..', 'a/../b', '.hidden', 'a/.git', 'x.php'] as $folder) {
            try {
                Files::cleanFolder($folder);
                $this->fail("$folder was accepted");
            } catch (MediaException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testATakenNameGetsANumberBeforeItsExtension(): void
    {
        $taken = ['a.png' => true, 'a-1.png' => true, 'noext' => true];

        $this->assertSame('a-2.png', Files::freeName('a.png', static fn (string $n): bool => isset($taken[$n])));
        $this->assertSame('noext-1', Files::freeName('noext', static fn (string $n): bool => isset($taken[$n])));
        $this->assertSame('b.png', Files::freeName('b.png', static fn (string $n): bool => isset($taken[$n])));
    }

    public function testATypeBelongsToOneKind(): void
    {
        $this->assertSame('image', Files::kindOf('image/png'));
        $this->assertSame('video', Files::kindOf('video/mp4'));
        $this->assertSame('audio', Files::kindOf('audio/mpeg'));
        $this->assertSame('document', Files::kindOf('application/pdf'));
        $this->assertSame('document', Files::kindOf('text/csv'));
        $this->assertSame('document', Files::kindOf('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame('other', Files::kindOf('application/zip'));

        $this->assertTrue(Files::accepts('image/png', 'image'));
        $this->assertFalse(Files::accepts('image/png', 'video'));
        $this->assertTrue(Files::accepts('application/zip', 'any'));
    }

    public function testOnlyMediaAndPlainTextAreShownInThePage(): void
    {
        $this->assertTrue(Files::isInline('image/svg+xml'));
        $this->assertTrue(Files::isInline('application/pdf'));
        $this->assertFalse(Files::isInline('text/html'));
        $this->assertFalse(Files::isInline('application/zip'));
    }

    public function testTheAddressOfAFileCarriesItsIdAndItsEncodedName(): void
    {
        $this->assertSame('media/12/my%20photo.png', Files::path(['id' => 12, 'name' => 'my photo.png']));
    }

    public function testFoldersAreMadeListedAndRemovedOnTheDisk(): void
    {
        $files = new Files(null, $this->dir);

        $this->assertSame('a', $files->createFolder('', 'a'));
        $this->assertSame('a/b', $files->createFolder('a', 'b'));
        $files->createFolder('', 'c');

        $this->assertSame(['a', 'c'], $files->subfolders());
        $this->assertSame(['a', 'a/b', 'c'], $files->allFolders());

        try {
            $files->createFolder('', 'a');
            $this->fail('a taken folder was made again');
        } catch (MediaException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(MediaException::class);
        $files->deleteFolder('a');
    }

    public function testAnEmptyFolderCanBeRemoved(): void
    {
        $files = new Files(null, $this->dir);
        $files->createFolder('', 'a');
        $files->deleteFolder('a');

        $this->assertSame([], $files->subfolders());
    }

    public function testTheUploadFolderCannotBeInsideThePublicFolder(): void
    {
        $inside = FCPATH . 'hyper_files_test_' . bin2hex(random_bytes(3));
        $files  = new Files(null, $inside);

        try {
            $files->root();
            $this->fail('A folder inside the public folder was accepted');
        } catch (MediaException) {
            $this->addToAssertionCount(1);
        } finally {
            if (is_dir($inside)) {
                rmdir($inside);
            }
        }
    }
}
