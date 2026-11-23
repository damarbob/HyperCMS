<?php

namespace PagingSystem\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * What the page module needs to know about the site's address space.
 */
class PagingSystem extends BaseConfig
{
    /**
     * The first parts of the addresses that never belong to a page. A path that starts with one of them, as a whole part,
     * is left to the routes of the application and the other modules, so `admin/users` is not looked up as a page and a
     * page called `admin-guide` is still reachable. `media` is the media library, and `api` the token API.
     *
     * @var list<string>
     */
    public array $excludedPrefixes = ['auth', 'api', 'admin', 'media', 'public', 'test'];

    /**
     * The path of the page that `/` shows. When no such page exists the welcome page of the application is shown instead.
     */
    public string $homePath = 'home';

    /**
     * The routing pattern that matches every address except the excluded ones.
     */
    public function routePattern(): string
    {
        $prefixes = array_map(static fn (string $prefix): string => preg_quote($prefix, '#'), array_values(array_filter($this->excludedPrefixes, static fn (string $p): bool => $p !== '')));
        if (ENVIRONMENT !== 'production') {
            $prefixes[] = preg_quote('.hyper-dev', '#');
        }

        return $prefixes === [] ? '(.*)' : '^(?!(?:' . implode('|', $prefixes) . ')(?:/|$))(.*)$';
    }
}
