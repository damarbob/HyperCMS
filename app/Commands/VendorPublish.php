<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use RuntimeException;

/**
 * Command class responsible for handling vendor asset publishing operations.
 *
 * This class provides functionality to publish vendor assets to the application's directories.
 * It can be used to automate the process of copying configuration files, assets, 
 * or other resources from vendor packages into the appropriate locations within the project.
 */
class VendorPublish extends BaseCommand
{
    protected $group = 'Hyper Publish';
    protected $name = 'hyper:vendor-publish';
    protected $description = 'Publish Node.js/Composer dependencies with optional dependency resolution.';

    protected $usage = 'hyper:vendor-publish [package] [options]';
    protected $options = [
        '-n, --nodejs' => 'Use Node.js to publish',
        '-c, --composer' => 'Use Composer to publish',
        '-ls, --list' => 'List available packages',
        '-f, --force' => 'Force overwrite',
        '-dep, --with-dependencies' => 'Also publish vendor dependencies',
    ];

    private $publishedPackages = [];
    private $dependencyStack = [];

    public function run(array $params)
    {
        $nodejs = array_key_exists('n', $params) || array_key_exists('nodejs', $params) || CLI::getOption('n') !== null || CLI::getOption('nodejs') !== null;
        $composer = array_key_exists('c', $params) || array_key_exists('composer', $params) || CLI::getOption('c') !== null || CLI::getOption('composer') !== null;
        $list = array_key_exists('ls', $params) || array_key_exists('list', $params) || CLI::getOption('ls') !== null || CLI::getOption('list') !== null;
        $force = array_key_exists('f', $params) || array_key_exists('force', $params) || CLI::getOption('f') !== null || CLI::getOption('force') !== null;
        $withDeps = array_key_exists('dep', $params) || array_key_exists('with-dependencies', $params) || CLI::getOption('dep') !== null || CLI::getOption('with-dependencies') !== null;

        // Validate options
        if ($nodejs && $composer) {
            CLI::error("Cannot use --nodejs and --composer simultaneously.");
            return;
        }

        if (!($nodejs || $composer)) {
            if ($list) {
                CLI::error("You must specify either --nodejs or --composer with --list");
            } else {
                CLI::error("You must specify either --nodejs or --composer");
            }
            return;
        }

        // Handle list option
        if ($list) {
            return $nodejs ? $this->listNodeJSPackages() : $this->listComposerPackages();
        }

        // Validate package name argument
        $package = array_shift($params) ?? null;
        if (!$package) {
            CLI::error("You must specify a package name");
            return;
        }

        // Perform publishing
        try {
            if ($nodejs) {
                $this->publishedPackages = [];
                return $this->publishNodeJSPackage($package, $force, $withDeps);
            }
            $this->publishedPackages = [];
            return $this->publishComposerPackage($package, $force, $withDeps);
        } catch (RuntimeException $e) {
            CLI::error($e->getMessage());
            return;
        }
    }

    protected function publishNodeJSPackage(string $package, bool $force, bool $withDeps): void
    {
        $normalizedName = $this->normalizePackageName($package);

        // Check for dependency cycles
        if (in_array($normalizedName, $this->dependencyStack)) {
            throw new RuntimeException("Dependency cycle detected: " .
                implode(' → ', $this->dependencyStack) . " → {$normalizedName}");
        }

        $this->dependencyStack[] = $normalizedName;

        // Process dependencies first
        if ($withDeps) {
            $dependencies = $this->getNpmDependencies($normalizedName);
            foreach ($dependencies as $dep) {
                if (!in_array($dep, $this->publishedPackages)) {
                    $this->publishNodeJSPackage($dep, $force, true);
                }
            }
        }

        $source = $this->getNodeJSPackagePath($normalizedName);
        $destination = $this->getNpmDestinationPath($normalizedName);

        if (in_array($normalizedName, $this->publishedPackages)) {
            array_pop($this->dependencyStack);
            return;
        }

        $this->validateNodeJSSource($source, $package, $normalizedName);
        $copied = $this->copyPackage($source, $destination, $force);

        $this->publishedPackages[] = $normalizedName;
        array_pop($this->dependencyStack);

        CLI::write("✅ Published [{$package}]", 'green');
        CLI::write("Copied {$copied} files to: " . str_replace(FCPATH, '', $destination), 'light_gray');
    }

    protected function publishComposerPackage(string $package, bool $force, bool $withDeps): void
    {
        // Check for dependency cycles
        if (in_array($package, $this->dependencyStack)) {
            throw new RuntimeException("Dependency cycle detected: " .
                implode(' → ', $this->dependencyStack) . " → {$package}");
        }

        $this->dependencyStack[] = $package;

        // Process dependencies first
        if ($withDeps) {
            $dependencies = $this->getComposerDependencies($package);
            foreach ($dependencies as $dep) {
                if (!in_array($dep, $this->publishedPackages)) {
                    $this->publishComposerPackage($dep, $force, true);
                }
            }
        }

        $source = ROOTPATH . "vendor/{$package}";
        $destination = FCPATH . "assets/vendor/{$package}";

        if (in_array($package, $this->publishedPackages)) {
            array_pop($this->dependencyStack);
            return;
        }

        $this->validateComposerSource($source);
        $copied = $this->copyPackage($source, $destination, $force);

        $this->publishedPackages[] = $package;
        array_pop($this->dependencyStack);

        CLI::write("✅ Published [{$package}]", 'green');
        CLI::write("Copied {$copied} files to: " . str_replace(FCPATH, '', $destination), 'light_gray');
    }

    protected function getNpmDependencies(string $normalizedName): array
    {
        $sourcePath = $this->getNodeJSPackagePath($normalizedName);
        $jsonPath = $sourcePath . '/package.json';

        if (!file_exists($jsonPath)) {
            CLI::write("⚠️ No package.json found for dependency {$normalizedName}", 'yellow');
            return [];
        }

        $json = json_decode(file_get_contents($jsonPath), true) ?? [];
        $dependencies = array_merge(
            $json['dependencies'] ?? [],
            $json['peerDependencies'] ?? []
        );

        return array_keys($dependencies);
    }

    protected function getComposerDependencies(string $package): array
    {
        $jsonPath = ROOTPATH . "vendor/{$package}/composer.json";

        if (!file_exists($jsonPath)) {
            CLI::write("⚠️ No composer.json found for dependency {$package}", 'yellow');
            return [];
        }

        $json = json_decode(file_get_contents($jsonPath), true) ?? [];
        return array_keys($json['require'] ?? []);
    }

    protected function normalizePackageName(string $package): string
    {
        // Keep scoped packages unchanged
        if (strpos($package, '@') === 0) {
            return $package;
        }

        // Extract npm package name (last segment after /)
        $parts = explode('/', $package);
        $name = end($parts);

        // Warn if we changed the name
        if ($name !== $package) {
            CLI::write("ℹ️ Using npm package name '{$name}' for '{$package}'", 'yellow');
        }

        return $name;
    }

    protected function getNodeJSPackagePath(string $normalizedName): string
    {
        // Scoped packages: @author/package
        if (strpos($normalizedName, '@') === 0) {
            return ROOTPATH . "node_modules/{$normalizedName}";
        }

        // Regular npm packages
        return ROOTPATH . "node_modules/{$normalizedName}";
    }

    protected function getNpmDestinationPath(string $normalizedName): string
    {
        // Convert to safe directory structure (scoped packages use _at_author/package)
        $path = preg_replace('/^@/', '_at_', $normalizedName);
        $path = str_replace(['/', '\\', '..'], DIRECTORY_SEPARATOR, $path);
        return FCPATH . "assets/vendor/{$path}";
    }

    protected function listNodeJSPackages(): void
    {
        $path = ROOTPATH . 'package.json';
        if (!is_file($path)) {
            CLI::error('package.json not found');
            return;
        }

        $json = json_decode(file_get_contents($path), true) ?? [];
        $dependencies = array_merge(
            $json['dependencies'] ?? [],
            $json['devDependencies'] ?? []
        );

        if (empty($dependencies)) {
            CLI::write('No Node.js dependencies found', 'yellow');
            return;
        }

        $this->displayPackageList($dependencies, 'nodejs');
    }

    protected function listComposerPackages(): void
    {
        $path = ROOTPATH . 'composer.json';
        if (!is_file($path)) {
            CLI::error('composer.json not found');
            return;
        }

        $json = json_decode(file_get_contents($path), true) ?? [];
        $dependencies = $json['require'] ?? [];

        if (empty($dependencies)) {
            CLI::write('No Composer dependencies found', 'yellow');
            return;
        }

        $this->displayPackageList($dependencies, 'composer');
    }

    protected function displayPackageList(array $dependencies, string $type): void
    {
        $headers = ['Package', 'Version', 'Description'];
        $rows = [];

        foreach ($dependencies as $name => $version) {
            $description = $this->getPackageDescription($name, $type);
            $rows[] = [$name, $version, $description];
        }

        CLI::table($rows, $headers);
        CLI::write("\nTotal: " . count($dependencies) . " packages");
    }

    protected function getPackageDescription(string $package, string $type): string
    {
        if ($type === 'nodejs') {
            $normalizedName = $this->normalizePackageName($package);
            $path = $this->getNodeJSPackagePath($normalizedName) . '/package.json';
        } else {
            $path = ROOTPATH . "vendor/{$package}/composer.json";
        }

        if (!is_file($path)) {
            return 'Description not available';
        }

        $json = json_decode(file_get_contents($path), true) ?? [];
        return $json['description'] ?? 'No description';
    }

    protected function validateNodeJSSource(string $path, string $originalName, string $normalizedName): void
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Node.js package not found: {$path}\n"
                . "Original input: {$originalName}\n"
                . "Resolved package: {$normalizedName}");
        }

        $targetDir = FCPATH . 'assets/vendor/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (!is_writable($targetDir)) {
            throw new RuntimeException('Target assets directory is not writable');
        }
    }

    protected function validateComposerSource(string $path): void
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Composer package not found: {$path}");
        }

        $targetDir = FCPATH . 'assets/vendor/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (!is_writable($targetDir)) {
            throw new RuntimeException('Target assets directory is not writable');
        }
    }

    protected function copyPackage(string $source, string $destination, bool $force): int
    {
        // Check if we've already published this package
        if (!$force && is_dir($destination)) {
            return 0; // Skip without prompt
        }

        // Force option overwrites existing packages
        if (is_dir($destination)) {
            if ($force || CLI::prompt("Package exists. Overwrite?", ['y', 'n'], 'required') === 'y') {
                $this->removeDirectory($destination);
            } else {
                CLI::write('Operation cancelled.', 'yellow');
                return 0;
            }
        }

        mkdir($destination, 0755, true);

        /** @var RecursiveIteratorIterator $iterator */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $copied = 0;
        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
                $copied++;
            }
        }

        return $copied;
    }

    protected function removeDirectory(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($path);
    }
}
