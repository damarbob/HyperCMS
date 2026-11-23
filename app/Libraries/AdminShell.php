<?php

namespace App\Libraries;

use App\Data\WriteVersions;
use CodeIgniter\HTTP\RequestInterface;
use Config\Hyper;

/**
 * What every admin page starts with: the data for the layout and the menu, built for the person who is signed in.
 *
 * `AdminController` and `App\Modules\ModuleController` both ask this, so a page of a module looks like a page of core
 * without the module touching a core controller.
 */
final class AdminShell
{
    /**
     * The number of live entries of every model, for the menu. One grouped query, kept in the cache until anything is
     * written, because the version of the write counter is part of the key.
     *
     * @return array<int, int>
     */
    private static function entryCounts(): array
    {
        $key = 'menu_entry_counts_' . (new WriteVersions())->global();

        return cache()->remember($key, 3600, static fn (): array => service('entryStore')->countsByModel());
    }

    /**
     * The data every admin page starts with: the page title and locale, the config for scripts, and the menu the signed-in
     * person may see.
     *
     * @return array<string, mixed>
     */
    public function data(RequestInterface $request): array
    {
        // --- Preinit data ---
        $models = service('modelStore')->all();

        // Build the menu as a nested array
        $menu = [
            lang('Admin.general') => [
                'dashboard' => [
                    'url' => base_url('admin/dashboard'),
                    'icon' => 'fa-solid fa-house',
                    'text' => lang("Admin.dashboard"),
                    'tooltip_content' => lang("Admin.dashboard"),
                    'tooltip_placement' => 'right',
                ],
            ],
            lang('Admin.data') => [
                'models' => [
                    'url' => base_url('admin/models'),
                    'icon' => 'fa-solid fa-circle-nodes',
                    'text' => lang("Admin.models"),
                    'hint' => lang('Admin.managex', ['x' => lang("Admin.models")]),
                    'tooltip_content' => lang("Admin.models"),
                    'tooltip_placement' => 'right',
                    'ability' => 'models.manage'
                ],
                'entries' => [
                    'url' => base_url('admin/entries'),
                    'icon' => 'fa-solid fa-table-list',
                    'text' => lang("Admin.entries"),
                    'hint' => lang('Admin.managex', ['x' => lang("Admin.entries")]),
                    'tooltip_content' => lang("Admin.entries"),
                    'tooltip_placement' => 'right',
                    'ability' => 'entries.browse'
                ],
            ],
            // This group contains a submenu (nested items)
            lang('Admin.others') => [
                'tokens' => [
                    'url' => base_url('admin/tokens'),
                    'icon' => 'key',
                    'text' => lang("Tokens.title"),
                    'tooltip_content' => lang("Tokens.title"),
                    'tooltip_placement' => 'right',
                    'ability' => 'tokens.manage'
                ],
                'settings' => [
                    // The parent link acting as a container (URL may be "#" or a clickable parent)
                    'url' => base_url('admin/settings'),
                    'icon' => 'fa-solid fa-cog',
                    'text' => lang("Admin.settings"),
                    'hint' => lang('Admin.adjustSiteSettings'),
                    'tooltip_content' => lang("Admin.settings"),
                    'tooltip_placement' => 'right',
                    'submenu' => [
                        'settings-general' => [
                            'url' => base_url('admin/settings'),
                            'text' => lang("Admin.general"),
                            'tooltip_content' => lang("Admin.general"),
                            'tooltip_placement' => 'right'
                        ],
                        'settings-models' => [
                            'url' => base_url('admin/settings/models'),
                            'text' => lang("Admin.models"),
                            'tooltip_content' => lang("Admin.models"),
                            'tooltip_placement' => 'right',
                            'ability' => 'models.manage'
                        ],
                        'settings-roles' => [
                            'url' => base_url('admin/roles'),
                            'text' => lang("Roles.title"),
                            'tooltip_content' => lang("Roles.title"),
                            'tooltip_placement' => 'right',
                            'ability' => 'roles.manage'
                        ],
                        'settings-abilities' => [
                            'url' => base_url('admin/abilities'),
                            'text' => lang("Roles.abTitle"),
                            'tooltip_content' => lang("Roles.abTitle"),
                            'tooltip_placement' => 'right',
                            'ability' => 'roles.manage'
                        ],
                        // You can also add additional submenu items or merge hook-driven items.
                    ]
                ]
            ]
        ];

        $modelsMenu = [];
        $counts     = self::entryCounts();
        foreach ($models as $model) {

            $entriesCount = $counts[(int) $model['id']] ?? 0;

            $tag = $entriesCount === 0 ?
                lang('Admin.(empty)') : ($entriesCount === 1 ?
                    lang('Admin.xEntry', ['x' => "{$entriesCount}"]) :
                    lang('Admin.xEntries', ['x' => "{$entriesCount}"])
                );

            // Skip the model when the user may not read its entries
            if (! can('entries.read', (int) $model['id'])) {
                continue;
            }

            $groupName = strtoupper(empty($model['group']) ? lang('Admin.models') : $model['group']);
            $modelsMenu[$groupName]["model-{$model['id']}"] = [
                'url' => base_url('admin/model/' . $model['id']),
                'text' => $model['name'],
                'tag' => $tag,
                'count' => $entriesCount,
                'icon' => $model['icon'],
                'tooltip_content' => $model['name'],
                'tooltip_placement' => 'right'
            ];
        }

        // Merge menu with modelsMenu
        $menu = array_merge($menu, $modelsMenu);

        // Additional menu for filter (to separate from the main menu)
        $additionalMenu = array_merge_recursive(service('hooks')->filter(hook('Backend.controller:menu:data'), []), service('modules')->menu());

        // Merge menu with additionalMenu
        $menu = array_merge_recursive($menu, $additionalMenu);

        // Menu reordering
        $menu = self::reorderMenuByKey($menu, lang('Admin.general'), 'start'); // Move the 'General' to the start
        $menu = self::reorderMenuByKey($menu, lang('Admin.others'), 'end'); // Move the 'Others' key to the end
        $menu = self::reorderMenuByKey($menu, lang('Admin.ai'), 1); // Move the 'AI' key to position 1 (second position)
        $menu = self::reorderMenuItemByIdAndKey($menu, lang('Admin.others'), 'settings', 'end'); // Always place settings menu at the very end

        $menu = self::filterMenuByAbility($menu);

        /* View data */

        return [
            'hyper' => [
                'config' => [
                    "appName" => config(Hyper::class)->appName,
                    "appVersion" => config(Hyper::class)->appVersion,
                    "baseUrl" => base_url(),
                    "environment" => ENVIRONMENT,
                    "csrfHeader" => csrf_header(),
                    "csrfToken" => csrf_token(),
                    "csrfHash" => csrf_hash(),
                    "locale" => service('request')->getLocale(),
                ],
                'lang' => dump_language_keys_grouped(),
            ],
            'title' => config(Hyper::class)->appName,
            'locale' => service('request')->getLocale(),
            'uri' => $request->getUri() . '/',
            'uriSegments' => $request->getUri()->getSegments(),
            'models' => $models,
            'menu' => $menu,
        ];

    }

    /**
     * Reorders an associative array so that the element with the specified key is moved to a new position.
     *
     * @param array  $menu      The original menu array.
     * @param string $groupKey  The key to reposition.
     * @param mixed  $position  The new position: 
     *                          - Use 'start' to move the key to the beginning.
     *                          - Use 'end' to move the key to the end.
     *                          - Use an integer to insert the key at that (zero-based) index.
     *
     * @return array The reordered array.
     */
    private static function reorderMenuByKey(array $menu, string $groupKey, $position): array
    {
        // If the target key is not present, return the original array.
        if (!array_key_exists($groupKey, $menu)) {
            return $menu;
        }

        // Remove the element from its current position.
        $element = $menu[$groupKey];
        unset($menu[$groupKey]);

        // Determine where to insert the element.
        if ($position === 'start') {
            // Insert at the beginning.
            return array_merge([$groupKey => $element], $menu);
        } elseif ($position === 'end') {
            // Insert at the end.
            return array_merge($menu, [$groupKey => $element]);
        } elseif (is_numeric($position)) {
            // Make sure position is an integer (zero-based index).
            $position = (int) $position;
            // If the position is zero or less, treat it as 'start'.
            if ($position <= 0) {
                return array_merge([$groupKey => $element], $menu);
            }
            $reordered = [];
            $i = 0;
            // Insert the element at the specified numeric position.
            foreach ($menu as $key => $value) {
                if ($i === $position) {
                    $reordered[$groupKey] = $element;
                }
                $reordered[$key] = $value;
                $i++;
            }
            // If the requested position is beyond the end, append the element.
            if ($position >= $i) {
                $reordered[$groupKey] = $element;
            }
            return $reordered;
        }

        // If none of the valid conditions match, return original.
        return $menu;
    }

    /**
     * Reorders a menu item within a given group based on the item’s ID (the array key).
     *
     * The menu is structured as:
     *   [ groupKey => [ itemId => itemData, ... ] ]
     *
     * @param array  $menu      The entire menu array.
     * @param string $groupKey  The key for the menu group to target.
     * @param string $targetId  The ID key of the item to reposition.
     * @param mixed  $position  The new position in the group:
     *                          - 'start' to move the item at the beginning,
     *                          - 'end'   to move it at the end,
     *                          - Or a numeric index (zero-based) to insert at a specific position.
     *
     * @return array The updated menu array.
     */
    private static function reorderMenuItemByIdAndKey(array $menu, string $groupKey, string $targetId, $position = 'end'): array
    {
        // Ensure the group exists and is an array.
        if (!isset($menu[$groupKey]) || !is_array($menu[$groupKey])) {
            return $menu;
        }

        $items = $menu[$groupKey];
        // Check if the target key exists.
        if (!array_key_exists($targetId, $items)) {
            return $menu;
        }

        // Extract the target element while preserving its key.
        $element = [$targetId => $items[$targetId]];
        // Remove it from the original array.
        unset($items[$targetId]);

        // Reinsert based on the desired position.
        if ($position === 'start') {
            // Insert at the beginning: merge with $element first.
            $items = $element + $items;
        } elseif ($position === 'end') {
            // Append: merge at the end.
            $items = $items + $element;
        } elseif (is_numeric($position)) {
            $position = (int) $position;
            // Use array_slice to preserve keys.
            $begin = array_slice($items, 0, $position, true);
            $end = array_slice($items, $position, null, true);
            $items = $begin + $element + $end;
        }

        // Save the reordered group back into the menu.
        $menu[$groupKey] = $items;
        return $menu;
    }

    /**
     * Recursively filters a nested menu array by what the signed-in user may do.
     *
     * At any level the function detects whether the array is "grouped" (the keys are group labels and the values are
     * sub-arrays of menu items) or is simply a list of menu items. Then, for each menu item:
     *
     *   - If "ability" is defined and the user does not have it, the item is removed. An ability nobody registered
     *     removes the item too.
     *   - If it is not defined, the item is unrestricted and is kept.
     *
     * Submenus (with key 'submenu') are processed recursively, and a group that becomes empty is removed.
     *
     * @param array $menu The menu array to filter.
     *
     * @return array The filtered menu array.
     */
    private static function filterMenuByAbility(array $menu): array
    {
        $isGrouped = false;
        if (! empty($menu)) {
            $first = reset($menu);
            if (! is_array($first) || ! isset($first['url'])) {
                $isGrouped = true;
            }
        }

        if ($isGrouped) {
            foreach ($menu as $groupName => $items) {
                $menu[$groupName] = self::filterMenuByAbility($items);
                if (empty($menu[$groupName])) {
                    unset($menu[$groupName]);
                }
            }

            return $menu;
        }

        foreach ($menu as $key => $item) {
            if (isset($item['ability'])) {
                try {
                    $allowed = can((string) $item['ability']);
                } catch (\InvalidArgumentException) {
                    $allowed = false;
                }
                if (! $allowed) {
                    unset($menu[$key]);

                    continue;
                }
            }

            if (isset($item['submenu']) && is_array($item['submenu'])) {
                $submenu = self::filterMenuByAbility($item['submenu']);
                if (! empty($submenu)) {
                    $menu[$key]['submenu'] = $submenu;
                } else {
                    unset($menu[$key]['submenu']);
                }
            }
        }

        return $menu;
    }
}
