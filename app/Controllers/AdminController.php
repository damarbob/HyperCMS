<?php

namespace App\Controllers;

use App\Libraries\SyntaxProcessor;
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
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // --- Preinit data ---
        $models = $this->modelsModel->getCustomBuilder()->get()->getResultArray();

        // Build the menu as a nested array
        $menu = [
            lang('Admin.general') => [
                'dashboard' => [
                    'url'               => base_url('admin/dashboard'),
                    'icon'              => 'fa-solid fa-house',
                    'text'              => lang("Admin.dashboard"),
                    'tooltip_content'   => lang("Admin.dashboard"),
                    'tooltip_placement' => 'right',
                ],
            ],
            lang('Admin.data') => [
                'models' => [
                    'url'               => base_url('admin/models'),
                    'icon'              => 'fa-solid fa-circle-nodes',
                    'text'              => lang("Admin.models"),
                    'hint'              => lang('Admin.managex', ['x' => lang("Admin.models")]),
                    'tooltip_content'   => lang("Admin.models"),
                    'tooltip_placement' => 'right',
                    'groups'            => 'superadmin'
                ],
                'entries' => [
                    'url'               => base_url('admin/entries'),
                    'icon'              => 'fa-solid fa-table-list',
                    'text'              => lang("Admin.entries"),
                    'hint'              => lang('Admin.managex', ['x' => lang("Admin.entries")]),
                    'tooltip_content'   => lang("Admin.entries"),
                    'tooltip_placement' => 'right',
                    // 'groups'            => 'superadmin,admin,developer'
                ],
            ],
            // This group contains a submenu (nested items)
            lang('Admin.others') => [
                'file-manager' => [
                    'url'               => base_url('admin/file-manager'),
                    'icon'              => 'fa-solid fa-folder-closed',
                    'text'              => lang("Admin.fileManager"),
                    'tooltip_content'   => lang("Admin.fileManager"),
                    'tooltip_placement' => 'right',
                    'groups_not'        => 'user'
                ],
                'settings' => [
                    // The parent link acting as a container (URL may be "#" or a clickable parent)
                    'url'               => base_url('admin/settings'),
                    'icon'              => 'fa-solid fa-cog',
                    'text'              => lang("Admin.settings"),
                    'hint'              => lang('Admin.adjustSiteSettings'),
                    'tooltip_content'   => lang("Admin.settings"),
                    'tooltip_placement' => 'right',
                    'submenu' => [
                        'settings-general' => [
                            'url'               => base_url('admin/settings'),
                            'text'              => lang("Admin.general"),
                            'tooltip_content'   => lang("Admin.general"),
                            'tooltip_placement' => 'right'
                        ],
                        'settings-models' => [
                            'url'               => base_url('admin/settings/models'),
                            'text'              => lang("Admin.models"),
                            'tooltip_content'   => lang("Admin.models"),
                            'tooltip_placement' => 'right',
                            'groups'            => 'superadmin'
                        ],
                        // You can also add additional submenu items or merge hook-driven items.
                    ]
                ]
            ]
        ];

        $modelsMenu = [];
        foreach ($models as $model) {

            $entriesCount = $this->entriesModel->getCustomBuilder()
                ->where('model_id', $model['id'])
                ->countAllResults();

            $tag = $entriesCount === 0 ?
                lang('Admin.(empty)') : ($entriesCount === 1 ?
                    lang('Admin.xEntry', ['x' => "{$entriesCount}"]) :
                    lang('Admin.xEntries', ['x' => "{$entriesCount}"])
                );

            // Skip if the user does not have access to the model
            $groupsArray = is_array($model['user_groups']) ? $model['user_groups'] : json_decode($model['user_groups'] ?? '');
            if (!empty($groupsArray) && !auth()->user()->inGroup(...$groupsArray)) {
                continue;
            }

            $groupName = strtoupper(empty($model['group']) ? lang('Admin.models') : $model['group']);
            $modelsMenu[$groupName]["model-{$model['id']}"] = [
                'url' => base_url('admin/model/' . $model['id']),
                'text' => $model['name'],
                'tag' => $tag,
                'icon' => $model['icon'],
                'tooltip_content'  => $model['name'],
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

        $menu = $this->filterMenuByUserGroups($menu, auth()->user()->getGroups());

        /* View data */

        $this->data = [
            'hyper' => [
                'config' => [
                    "baseUrl"       => base_url(),
                    "environment"   => ENVIRONMENT,
                    "csrfHeader"    => csrf_header(),
                    "csrfToken"     => csrf_token(),
                    "csrfHash"      => csrf_hash(),
                    "locale"        => service('request')->getLocale(),
                ],
                'lang' => dump_language_keys_grouped(),
            ],
            'title'       => config(Hyper::class)->appName,
            'locale'      => service('request')->getLocale(),
            'uri'         => $request->getUri() . '/',
            'uriSegments' => $request->getUri()->getSegments(),
            'models'      => $models,
            'menu'        => $menu,
        ];

        /* End of view data */

        /* Testing */

        $syntaxProcessor = new SyntaxProcessor();
        if (false):
            log_message('debug', json_encode($syntaxProcessor->process('
            [
                {
                    "type": "data",
                    "content": "hooks"
                    },
                    {
                        "type": "data",
                        "content": {
                            "table": "entries",
                            "select": "id as value, fields as label",
                            "orderby": "id ASC"
                            }
                    }
            ]
            ')));
        endif;

        /* End of testing */
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
            $position = (int)$position;
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
            $position = (int)$position;
            // Use array_slice to preserve keys.
            $begin = array_slice($items, 0, $position, true);
            $end   = array_slice($items, $position, null, true);
            $items = $begin + $element + $end;
        }

        // Save the reordered group back into the menu.
        $menu[$groupKey] = $items;
        return $menu;
    }

    /**
     * Recursively filters a nested menu array based on user group conditions.
     *
     * At any level the function detects whether the array is "grouped" (the keys
     * are group labels and the values are sub-arrays of menu items) or is simply a list
     * of menu items. Then, for each menu item:
     *
     *   - If "groups_not" is defined and any of the user's groups match, the item is removed.
     *   - If "groups" is defined (and groups_not did not trigger removal), then at least one
     *     user group must be present in that list; otherwise the item is removed.
     *   - If neither key exists, the item is assumed to be unrestricted and is kept.
     *
     * Submenus (with key 'submenu') are processed recursively.
     *
     * @param array $menu       The menu array to filter.
     * @param array $userGroups An array of current user groups.
     *
     * @return array The filtered menu array.
     */
    protected static function filterMenuByUserGroups(array $menu, array $userGroups): array
    {
        // Determine if this level is grouped or a simple list.
        // We assume that a grouped level does not have menu items with a 'url' key
        // directly at the top level, whereas a list of items will have each item with a 'url'.
        $isGrouped = false;
        if (!empty($menu)) {
            $first = reset($menu);
            if (!is_array($first) || !isset($first['url'])) {
                $isGrouped = true;
            }
        }

        if ($isGrouped) {
            // Process each group by filtering its items recursively.
            foreach ($menu as $groupName => $items) {
                $menu[$groupName] = self::filterMenuByUserGroups($items, $userGroups);
                // Remove this group if it becomes empty.
                if (empty($menu[$groupName])) {
                    unset($menu[$groupName]);
                }
            }
            return $menu;
        } else {
            // The array is a list of menu items.
            foreach ($menu as $key => $item) {
                $keep = true;

                // Check "groups_not" condition first.
                if (isset($item['groups_not'])) {
                    $groupsNot = array_map('trim', explode(',', $item['groups_not']));
                    if (count(array_intersect($userGroups, $groupsNot)) > 0) {
                        $keep = false;
                    }
                }

                // Check "groups" condition (only if not removed by groups_not).
                if ($keep && isset($item['groups'])) {
                    $requiredGroups = array_map('trim', explode(',', $item['groups']));
                    if (count(array_intersect($userGroups, $requiredGroups)) === 0) {
                        $keep = false;
                    }
                }

                if (!$keep) {
                    unset($menu[$key]);
                    continue;
                }

                // Process submenu recursively if it exists.
                if (isset($item['submenu']) && is_array($item['submenu'])) {
                    $filteredSubmenu = self::filterMenuByUserGroups($item['submenu'], $userGroups);
                    if (!empty($filteredSubmenu)) {
                        $menu[$key]['submenu'] = $filteredSubmenu;
                    } else {
                        unset($menu[$key]['submenu']);
                    }
                }
            }
            return $menu;
        }
    }
}
