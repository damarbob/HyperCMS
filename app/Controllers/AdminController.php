<?php

namespace App\Controllers;

use App\Data\WriteVersions;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Hyper;
use Psr\Log\LoggerInterface;

/**
 * Class AdminController
 *
 * AdminController provides a convenient place for loading components
 * and performing functions that are needed by all your admin controllers.
 * Extend this class in any new controllers:
 *     class Home extends AdminController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class AdminController extends BaseController
{

    /**
     * The number of live entries of every model, for the menu. One grouped query, kept in the cache until anything is
     * written, because the version of the write counter is part of the key.
     *
     * @return array<int, int>
     */
    private function entryCounts(): array
    {
        $key = 'menu_entry_counts_' . (new WriteVersions())->global();

        return cache()->remember($key, 3600, static fn (): array => service('entryStore')->countsByModel());
    }

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

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
                'file-manager' => [
                    'url' => base_url('admin/file-manager'),
                    'icon' => 'fa-solid fa-folder-closed',
                    'text' => lang("Admin.fileManager"),
                    'tooltip_content' => lang("Admin.fileManager"),
                    'tooltip_placement' => 'right',
                    'ability' => 'media.manage'
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
        $counts     = $this->entryCounts();
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
        $additionalMenu = $this->hooks->filter(hook('Backend.controller:menu:data'), []);

        // Merge menu with additionalMenu
        $menu = array_merge_recursive($menu, $additionalMenu);

        // Menu reordering
        $menu = $this->reorderMenuByKey($menu, lang('Admin.general'), 'start'); // Move the 'General' to the start
        $menu = $this->reorderMenuByKey($menu, lang('Admin.others'), 'end'); // Move the 'Others' key to the end
        $menu = $this->reorderMenuByKey($menu, lang('Admin.ai'), 1); // Move the 'AI' key to position 1 (second position)
        $menu = $this->reorderMenuItemByIdAndKey($menu, lang('Admin.others'), 'settings', 'end'); // Always place settings menu at the very end

        $menu = self::filterMenuByAbility($menu);

        /* View data */

        $this->data = [
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

        /* End of view data */
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
    protected static function reorderMenuByKey(array $menu, string $groupKey, $position): array
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
    protected static function reorderMenuItemByIdAndKey(array $menu, string $groupKey, string $targetId, $position = 'end'): array
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
    protected static function filterMenuByAbility(array $menu): array
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
