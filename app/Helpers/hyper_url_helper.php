<?php

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
     * Check if the first URL contains the second URL as its beginning (after normalization).
     *
     * This is useful when the current URL may go deeper than a base URL.
     * 
     * For example, if the full URL is:
     *     http://localhost:8080/index.php/admin/models/details
     * and the base URL is:
     *     http://localhost:8080/admin/models
     * then the condition returns true.
     *
     * @param string $fullUrl The complete URL.
     * @param string $baseUrl The URL to check as a base.
     * @return bool True if $fullUrl (normalized) starts with $baseUrl (normalized), false otherwise.
     */
    function url_contains(string $fullUrl, string $baseUrl): bool
    {
        $normalizedFullUrl = normalize_url($fullUrl);
        $normalizedBaseUrl = normalize_url($baseUrl);
        return strpos($normalizedFullUrl, $normalizedBaseUrl) === 0;
    }
}
