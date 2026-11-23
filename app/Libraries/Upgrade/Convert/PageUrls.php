<?php

namespace App\Libraries\Upgrade\Convert;

/**
 * Gives a page that has a title but no address the address its title made. The page module used to find such a page by the
 * title in the address bar, which cannot be an indexed read, so the address is written into the page and the lookup by
 * address finds it as before. It works on the values of one entry and touches no database.
 */
final class PageUrls
{
    public const MAIN = 'frontend:main';

    /**
     * @param array<string, mixed> $fields The values of a page entry.
     *
     * @return array<string, mixed> The same values, with `hyper_page_url` filled when it was empty on a main page.
     */
    public static function fill(array $fields): array
    {
        if (($fields['hyper_page_hook_id'] ?? null) !== self::MAIN) {
            return $fields;
        }
        $url   = $fields['hyper_page_url'] ?? null;
        $title = $fields['hyper_title'] ?? null;
        if (is_string($url) && trim($url) !== '') {
            return $fields;
        }
        if (! is_string($title) || trim($title) === '') {
            return $fields;
        }

        helper('url');
        $slug = url_title(trim($title), '-', true);
        if ($slug !== '') {
            $fields['hyper_page_url'] = $slug;
        }

        return $fields;
    }
}
