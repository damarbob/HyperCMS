<?php

namespace App\Libraries;

/**
 * Turns the menu the admin controller builds, a list of groups of items, into what the rail draws.
 *
 * The controller's menu carries Font Awesome class names for icons and the current page is only known as a URI. This
 * class maps each icon to one of the sprite, marks the item of the current page, and nests the submenu items under
 * their parent. It reads arrays and returns arrays, so it can be tested without a request.
 */
final class RailMenu
{
    /**
     * Icons of the old menu by their last class, mapped to the sprite. Anything else draws a document.
     */
    private const ICONS = [
        'house'        => 'dashboard',
        'gauge'        => 'dashboard',
        'circle-nodes' => 'models',
        'cubes'        => 'models',
        'table-list'   => 'entries',
        'layer-group'  => 'entries',
        'folder-closed' => 'media',
        'folder'       => 'media',
        'image'        => 'media',
        'images'       => 'media',
        'cog'          => 'settings',
        'gear'         => 'settings',
        'sliders'      => 'settings',
        'users'        => 'users',
        'user'         => 'user',
        'user-gear'    => 'users',
        'file'         => 'doc',
        'file-lines'   => 'doc',
        'file-text'    => 'doc',
        'newspaper'    => 'doc',
        'table-columns' => 'pages',
        'table'        => 'table',
        'bolt'         => 'bolt',
        'person'       => 'user',
        'users-gear'   => 'users',
        'window-maximize' => 'pages',
        'pen-ruler'    => 'pages',
    ];

    /**
     * The icons of the sprite, so a menu that already names one keeps it.
     */
    private const SPRITE = ['dashboard', 'entries', 'doc', 'models', 'media', 'pages', 'users', 'user', 'table', 'bolt', 'settings', 'search', 'history', 'key', 'lock', 'mail', 'folder', 'upload', 'download'];

    /**
     * @param array<string, array<string, array<string, mixed>>> $menu  Groups of items, as `AdminController` builds them.
     * @param string                                             $path The path of the current request, such as `admin/model/3`.
     *
     * @return list<array{label: string, items: list<array{url: string, text: string, icon: string, count: ?int, current: bool, open: bool, children: list<array{url: string, text: string, current: bool}>}>}>
     */
    public static function prepare(array $menu, string $path): array
    {
        $current = self::currentUrl($menu, $path);

        $groups = [];
        $first  = true;
        foreach ($menu as $name => $items) {
            $rows = [];
            foreach ($items as $item) {
                if (! is_array($item) || ! isset($item['url'], $item['text'])) {
                    continue;
                }

                $children = [];
                foreach ($item['submenu'] ?? [] as $child) {
                    if (is_array($child) && isset($child['url'], $child['text'])) {
                        $children[] = ['url' => (string) $child['url'], 'text' => strip_tags((string) $child['text']), 'current' => (string) $child['url'] === $current];
                    }
                }
                $isCurrent = (string) $item['url'] === $current;

                $rows[] = [
                    'url'      => (string) $item['url'],
                    'text'     => strip_tags((string) $item['text']),
                    'icon'     => self::icon((string) ($item['icon'] ?? '')),
                    'count'    => isset($item['count']) ? (int) $item['count'] : null,
                    'current'  => $isCurrent,
                    'open'     => $isCurrent || in_array(true, array_column($children, 'current'), true),
                    'children' => $children,
                ];
            }

            if ($rows === []) {
                continue;
            }

            // The first group holds the dashboard and needs no heading. The others are named in sentence case, because
            // models put their group names in capitals.
            $groups[] = ['label' => $first ? '' : self::sentence(strip_tags((string) $name)), 'items' => $rows];
            $first    = false;
        }

        return $groups;
    }

    /**
     * Map an icon class of the old menu, such as `fa-solid fa-house`, to an icon of the sprite.
     */
    public static function icon(string $class): string
    {
        $parts = preg_split('/\s+/', trim($class)) ?: [];
        $last  = (string) end($parts);

        if (in_array($last, self::SPRITE, true)) {
            return $last;
        }

        return self::ICONS[preg_replace('/^fa-/', '', $last)] ?? 'doc';
    }

    /**
     * The URL of the item that stands for the current page, which is the one whose path is the longest prefix of it, so
     * `admin/models` does not light up on `admin/model/3`.
     *
     * @param array<string, array<string, array<string, mixed>>> $menu
     */
    private static function currentUrl(array $menu, string $path): ?string
    {
        $segments = self::segments($path);
        $best     = null;
        $bestSize = -1;

        foreach ($menu as $items) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $urls = [$item['url'] ?? ''];
                foreach ($item['submenu'] ?? [] as $child) {
                    $urls[] = is_array($child) ? ($child['url'] ?? '') : '';
                }
                foreach ($urls as $url) {
                    $base = self::segments((string) parse_url((string) $url, PHP_URL_PATH));
                    if ($base === [] || count($base) > count($segments) || array_slice($segments, 0, count($base)) !== $base) {
                        continue;
                    }
                    if (count($base) > $bestSize) {
                        $best     = (string) $url;
                        $bestSize = count($base);
                    }
                }
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        $path = (string) preg_replace('#^/?index\.php#', '', $path);

        return array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));
    }

    private static function sentence(string $text): string
    {
        $text = trim($text);
        // Model groups are written in capitals. A short word such as AI is an acronym and stays as it is.
        if (mb_strlen($text) <= 3 || $text !== mb_strtoupper($text)) {
            return $text;
        }
        $text = mb_strtolower($text);

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
