<?php

/**
 * Returns the URL to a module's published assets.
 *
 * @param string            $module    The module name (e.g. "Blog").
 * @param string|array      $path      Optional sub‐path (e.g. "css/app.css" or ['css','app.css']).
 * @param string|null       $scheme    URI scheme (http, https). Null = protocol‐relative.
 *
 * @return string
 */
if (! function_exists('module_assets_url')) {
    function module_assets_url(string $module, $path = '', ?string $scheme = null): string
    {
        // Build the relative URI: /assets/modules/{Module}/{path}
        $relative = 'assets/modules/'
            . trim($module, '/')
            . '/'
            . (is_array($path) ? implode('/', $path) : ltrim($path, '/'));

        // Delegate to base_url under the hood
        return base_url($relative, $scheme);
    }
}

/**
 * Returns the URL to a module's dev‐mode published assets.
 *
 * @param string            $module    The module name (e.g. "Blog").
 * @param string|array      $path      Optional sub‐path (e.g. "css/app.css" or ['css','app.css']).
 * @param string|null       $scheme    URI scheme (http, https). Null = protocol‐relative.
 *
 * @return string
 */
if (! function_exists('module_dev_assets_url')) {
    function module_dev_assets_url(string $module, $path = '', ?string $scheme = null): string
    {
        $relative = 'assets/.hyper-dev/modules/'
            . trim($module, '/')
            . '/'
            . (is_array($path) ? implode('/', $path) : ltrim($path, '/'));

        return base_url($relative, $scheme);
    }
}

/**
 * Environment‐aware URL to a module's assets:
 * - In production -> module_assets_url()
 * - Otherwise      -> module_dev_assets_url()
 * 
 * @param string            $module    The module name (e.g. "Blog").
 * @param string|array      $path      Optional sub‐path (e.g. "css/app.css" or ['css','app.css']).
 * @param string|null       $scheme    URI scheme (http, https). Null = protocol‐relative.
 *
 * @return string
 */
if (! function_exists('module_assets_url_env')) {
    function module_assets_url_env(string $module, $path = '', ?string $scheme = null): string
    {
        if (ENVIRONMENT === 'production') {
            return module_assets_url($module, $path, $scheme);
        }

        return module_dev_assets_url($module, $path, $scheme);
    }
}

if (! function_exists('normalize_url')) {
    /**
     * Normalize a URL by removing '/index.php' and trailing slashes.
     *
     * @param string $url The URL to normalize.
     * @return string The normalized URL.
     */
    function normalize_url(string $url): string
    {
        // Remove '/index.php'
        $url = str_replace('/index.php', '', $url);
        // Remove trailing slashes
        $url = rtrim($url, '/');
        return $url;
    }
}

if (! function_exists('urls_match')) {
    /**
     * Compare two URLs after normalization.
     *
     * @param string $url1 The first URL.
     * @param string $url2 The second URL.
     * @return bool True if the normalized URLs match, false otherwise.
     */
    function urls_match(string $url1, string $url2): bool
    {
        return normalize_url($url1) === normalize_url($url2);
    }
}

if (! function_exists('url_contains')) {
    /**
     * Check if a given full URL contains the base URL by comparing their path segments.
     *
     * This function normalizes both URLs, splits them into segments, and compares
     * the base URL’s segments against the corresponding segments of the full URL.
     * It returns true only if every segment in the base URL exactly matches the
     * corresponding segment in the full URL.
     *
     * @param string $fullUrl The complete URL.
     * @param string $baseUrl The URL to check as a base.
     * @return bool True if $fullUrl starts with $baseUrl (compared segment-wise), false otherwise.
     */
    function url_contains(string $fullUrl, string $baseUrl): bool
    {
        // Normalize the URLs first.
        $normalizedFullUrl = normalize_url($fullUrl);
        $normalizedBaseUrl = normalize_url($baseUrl);

        // Remove any query strings or fragments if necessary. For instance, you could use parse_url() here:
        $fullPath = parse_url($normalizedFullUrl, PHP_URL_PATH);
        $basePath = parse_url($normalizedBaseUrl, PHP_URL_PATH);

        // Break the paths into segments; trim leading and trailing slashes.
        $fullSegments = array_values(array_filter(explode('/', trim($fullPath, '/'))));
        $baseSegments = array_values(array_filter(explode('/', trim($basePath, '/'))));

        // If the base has more segments than the full URL, it cannot be a prefix.
        if (count($baseSegments) > count($fullSegments)) {
            return false;
        }

        // Compare each segment in the base with the corresponding segment in the full URL.
        foreach ($baseSegments as $index => $segment) {
            if ($fullSegments[$index] !== $segment) {
                return false;
            }
        }

        return true;
    }
}
