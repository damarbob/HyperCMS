<?php

/**
 * The one way to build the front end and the release package of HyperCMS.
 *
 *   php tools/build.php assets
 *       Installs the Node packages from package-lock.json and publishes each one to public/assets/vendor.
 *       The Dockerfile runs this, so an image and a package get the same files.
 *
 *   php tools/build.php package [--out=dist] [--allow-dirty] [--with-untracked] [--keep-stage]
 *       Builds hypercms-<version>.zip for a host with no Composer, no Node and no shell. It copies the
 *       tracked files a site needs into a clean folder, installs Composer packages there without the
 *       development ones, publishes the front end, and zips the result.
 *
 * Run it on a developer machine with PHP (zip extension), Composer, Node and Git on the path.
 */

require __DIR__ . '/PackageRules.php';

const ROOT = __DIR__ . '/..';

exit(main($argv));

function main(array $argv): int
{
    $command = $argv[1] ?? '';
    $options = parseOptions(array_slice($argv, 2));

    try {
        return match ($command) {
            'assets'  => assets(realpath(ROOT)) ? 0 : 1,
            'package' => package($options) ? 0 : 1,
            default   => usage(),
        };
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'Build stopped. ' . $e->getMessage() . PHP_EOL);

        return 1;
    }
}

function usage(): int
{
    fwrite(STDERR, "Usage:\n  php tools/build.php assets\n  php tools/build.php package [--out=dist] [--allow-dirty] [--with-untracked] [--keep-stage]\n");

    return 2;
}

/**
 * @return array<string, string|true>
 */
function parseOptions(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (! str_starts_with($argument, '--')) {
            throw new RuntimeException("Unknown argument $argument.");
        }
        $parts                  = explode('=', substr($argument, 2), 2);
        $options[$parts[0]]     = $parts[1] ?? true;
    }

    return $options;
}

/**
 * Runs a command in a folder, shows its output and returns whether it succeeded.
 */
function run(string $command, string $folder): bool
{
    $previous = getcwd();
    chdir($folder);
    echo '> ' . $command . PHP_EOL;
    passthru($command, $code);
    chdir($previous);

    return $code === 0;
}

function capture(string $command, string $folder): string
{
    $previous = getcwd();
    chdir($folder);
    $output = shell_exec($command . ' 2>&1');
    chdir($previous);

    return (string) $output;
}

/**
 * Installs the Node packages and publishes every top level one, with what it depends on, into public/assets/vendor.
 */
function assets(string $folder): bool
{
    if (! is_file($folder . '/package.json')) {
        throw new RuntimeException("There is no package.json in $folder.");
    }

    if (! run('npm ci', $folder)) {
        return false;
    }

    $package = json_decode((string) file_get_contents($folder . '/package.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach (array_keys($package['dependencies'] ?? []) as $name) {
        if (! run('php spark hyper:vendor-publish ' . escapeshellarg($name) . ' --nodejs --force --with-dependencies', $folder)) {
            return false;
        }
    }

    return true;
}

function appVersion(): string
{
    $source = (string) file_get_contents(ROOT . '/app/Config/Hyper.php');

    if (! preg_match("/\\\$appVersion\\s*=\\s*'([^']+)'/", $source, $match)) {
        throw new RuntimeException('Could not read appVersion from app/Config/Hyper.php.');
    }

    return $match[1];
}

/**
 * @param array<string, string|true> $options
 */
function package(array $options): bool
{
    $root = realpath(ROOT);

    $changed = trim(capture('git status --porcelain --untracked-files=no', $root));
    if ($changed !== '' && ! isset($options['allow-dirty'])) {
        throw new RuntimeException("The working copy has uncommitted changes to tracked files. Commit them, or pass --allow-dirty to package them anyway.\n$changed");
    }

    $version = appVersion();
    $out     = isset($options['out']) && is_string($options['out']) ? $options['out'] : 'dist';
    $out     = isAbsolute($out) ? $out : $root . '/' . $out;
    if (! is_dir($out) && ! mkdir($out, 0o755, true)) {
        throw new RuntimeException("Could not create $out.");
    }

    $stage = sys_get_temp_dir() . '/hypercms-package-' . bin2hex(random_bytes(4));
    mkdir($stage, 0o755, true);
    echo "Staging in $stage" . PHP_EOL;

    try {
        copyTracked($root, $stage, isset($options['with-untracked']));

        // The front end needs these two files to install and publish. They do not ship.
        copy($root . '/package.json', $stage . '/package.json');
        copy($root . '/package-lock.json', $stage . '/package-lock.json');

        if (! run('composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist', $stage)) {
            throw new RuntimeException('Composer could not install the packages.');
        }
        if (! assets($stage)) {
            throw new RuntimeException('The front end could not be built.');
        }

        removeTree($stage . '/node_modules');
        unlink($stage . '/package.json');
        unlink($stage . '/package-lock.json');

        // Running spark above wrote logs and caches. Only the index.html and .htaccess files of writable/ ship.
        clearWritable($stage . '/writable');

        foreach (PackageRules::WRITABLE_FOLDERS as $folder) {
            if (! is_dir($stage . '/' . $folder)) {
                mkdir($stage . '/' . $folder, 0o755, true);
            }
        }

        file_put_contents($stage . '/VERSION', $version . PHP_EOL);

        // The hosting guide goes into the package once it exists.
        if (is_file($root . '/docs/hosting.md')) {
            copy($root . '/docs/hosting.md', $stage . '/HOSTING.md');
        }

        $zipPath = $out . '/hypercms-' . $version . '.zip';
        zipFolder($stage, 'hypercms', $zipPath);
        verifyZip($zipPath);
    } finally {
        if (! isset($options['keep-stage'])) {
            removeTree($stage);
        }
    }

    printf("Built %s (%.1f MB)\n", $zipPath, filesize($zipPath) / 1048576);

    return true;
}

function isAbsolute(string $path): bool
{
    return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path) === 1;
}

/**
 * Copies the tracked files a site needs. With `$untracked` it also takes new files that are not ignored, which is only for trying
 * a package before the files are committed, never for a release.
 */
function copyTracked(string $root, string $stage, bool $untracked = false): void
{
    $paths = explode("\0", capture('git ls-files -z', $root));
    if ($untracked) {
        // Only code and assets, never the loose notes and settings copies a working folder collects in its root.
        foreach (explode("\0", capture('git ls-files -z --others --exclude-standard', $root)) as $path) {
            if (preg_match('#^(app|modules|public|tools)/#', $path) === 1) {
                $paths[] = $path;
            }
        }
    }

    foreach ($paths as $path) {
        if (! PackageRules::includes($path) || ! is_file($root . '/' . $path)) {
            continue;
        }

        $target = $stage . '/' . $path;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0o755, true);
        }
        copy($root . '/' . $path, $target);
    }
}

function removeTree(string $folder): void
{
    if (! is_dir($folder)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($folder);
}

function clearWritable(string $folder): void
{
    if (! is_dir($folder)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            // Folders the run made, such as writable/stardust, go once empty. The tracked ones hold an index.html.
            @rmdir($item->getPathname());
        } elseif (! in_array($item->getFilename(), ['index.html', '.htaccess'], true)) {
            unlink($item->getPathname());
        }
    }
}

function zipFolder(string $folder, string $top, string $zipPath): void
{
    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Could not create $zipPath.");
    }

    $base  = strlen(rtrim(str_replace('\\', '/', $folder), '/')) + 1;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($items as $item) {
        $name = $top . '/' . substr(str_replace('\\', '/', $item->getPathname()), $base);

        if ($item->isDir()) {
            $zip->addEmptyDir($name);
            $zip->setExternalAttributesName($name . '/', ZipArchive::OPSYS_UNIX, 0o40755 << 16);
        } else {
            $zip->addFile($item->getPathname(), $name);
            $mode = $name === $top . '/spark' ? 0o100755 : 0o100644;
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $mode << 16);
        }
    }

    if (! $zip->close()) {
        throw new RuntimeException("Could not write $zipPath.");
    }
}

/**
 * Opens the finished zip and checks that what a site needs is in it and what must not ship is not.
 */
function verifyZip(string $zipPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException("Could not read back $zipPath.");
    }

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    $required = [
        'hypercms/vendor/autoload.php',
        'hypercms/public/index.php',
        'hypercms/public/.htaccess',
        'hypercms/public/assets/vendor/htmx.org/dist/htmx.min.js',
        'hypercms/public/assets/ui/ui.js',
        'hypercms/app/Config/Hyper.php',
        'hypercms/spark',
        'hypercms/env',
        'hypercms/writable/uploads/index.html',
        'hypercms/VERSION',
    ];
    foreach ($required as $name) {
        if (! in_array($name, $names, true)) {
            throw new RuntimeException("The package is missing $name.");
        }
    }

    foreach ($names as $name) {
        if (str_ends_with($name, '/')) {
            continue;
        }

        $forbidden = preg_match('#^hypercms/(\.env|node_modules/|tests/|tools/|\.git|\.agent/|\.hyper-dev)#', $name) === 1;
        $written   = str_starts_with($name, 'hypercms/writable/')
            && ! in_array(basename($name), ['index.html', '.htaccess'], true);

        if ($forbidden || $written) {
            throw new RuntimeException("The package holds $name, which must not ship.");
        }
    }
}
