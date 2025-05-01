<?php

use App\Hyper\HyperHook;

if (! function_exists('hook')) {
    /**
     * Retrieve a hook value for the given key from any registered namespace.
     *
     * Usage: hook('Frontend.header') will search all namespaces for a file
     * named 'Hooks/Frontend.php' and return the value for the key 'header'.
     *
     * @param string $key The hook key (e.g., "Frontend.header")
     * @param array  $params Optional parameters to replace any placeholders in the hook's value.
     *
     * @return string The hook value, or an empty string if not found.
     */
    function hook(string $key, array $params = []): string
    {
        // Split the key ("Frontend.header") into $group and $lineKey.
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            return ''; // Invalid format.
        }
        list($group, $lineKey) = $parts;

        // Use service('locator') to locate the hook file in all namespaces
        /** @var CodeIgniter\Autoloader\FileLocatorInterface */
        $locator = service('locator');
        $files = $locator->search('Hooks/' . $group . '.php');

        if (empty($files)) {
            log_message('error', 'Hook file not found for group ' . $group . ':' . json_encode($files));
            return ''; // Hook group file not found in any namespace
        }

        // Use the first found file (you might want to implement priority logic here)
        $file = reset($files);

        // Use a static cache to prevent loading the same file multiple times.
        static $hooksCache = [];
        if (! isset($hooksCache[$group])) {
            // The hook file must return an array.
            $hooksCache[$group] = require $file;
        }

        $hooks = $hooksCache[$group];

        // Check if the key exists and that it's an instance of HyperHook.
        if (! isset($hooks[$lineKey]) || ! $hooks[$lineKey] instanceof HyperHook) {
            return '';
        }

        $hookValue = $hooks[$lineKey];

        // Replace placeholders if any are provided.
        if (! empty($params)) {
            foreach ($params as $find => $replace) {
                // For example, if the hook value contains "{name}", it will be replaced.
                $hookValue = str_replace('{' . $find . '}', $replace, $hookValue);
            }
        }

        // Return the hook's name (assuming getName() is the method to access it)
        return $hookValue->getName();
    }
}

if (!function_exists('dump_hooks')) {
    /**
     * Dump hooks from files in the Hooks directories across all namespaces.
     *
     * @param string|null $group If specified, only returns hooks from this group (e.g., 'Frontend')
     * @return array<string, array> An associative array of hooks
     */
    function dump_hooks(?string $group = null): array
    {
        static $allHooks = null;

        // If no group specified and we have cached all hooks, return them
        if ($group === null && $allHooks !== null) {
            return $allHooks;
        }

        // If a group is specified and we have it cached, return just that group
        if ($group !== null && $allHooks !== null && isset($allHooks[$group])) {
            return [$group => $allHooks[$group]];
        }

        /** @var CodeIgniter\Autoloader\FileLocatorInterface */
        $locator = service('locator');
        $hooks = [];

        if ($group === null) {
            // Get all hook files in all namespaces
            $files = $locator->listFiles('Hooks/');

            foreach ($files as $file) {
                $currentGroup = pathinfo($file, PATHINFO_FILENAME);
                $loadedHooks = require $file;
                if (is_array($loadedHooks)) {
                    $hooks[$currentGroup] = $loadedHooks;
                }
            }

            // Cache all hooks for future calls
            $allHooks = $hooks;
        } else {
            // Search specifically for the requested group
            $files = $locator->search('Hooks/' . $group . '.php');

            if (!empty($files)) {
                $file = reset($files); // Get the first found file
                $loadedHooks = require $file;
                if (is_array($loadedHooks)) {
                    $hooks[$group] = $loadedHooks;

                    // Cache this group in the allHooks cache
                    if ($allHooks === null) {
                        $allHooks = [];
                    }
                    $allHooks[$group] = $loadedHooks;
                }
            }
        }

        return $hooks;
    }
}
