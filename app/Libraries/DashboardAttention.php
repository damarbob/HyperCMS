<?php

namespace App\Libraries;

/**
 * Works out what the dashboard should point out: things that are true right now and that someone with the right to fix them
 * would want to know. It takes plain arrays and reads nothing, so it can be tested without a database. A thing that is not
 * true gives no item, and with no item the dashboard draws no strip at all.
 */
final class DashboardAttention
{
    /** The most links one item carries, so a site with many models does not fill the strip. */
    private const MAX_LINKS = 4;

    /**
     * @param list<array{id: int, name: string, label_field: ?string, fields: mixed}> $models   The models the person may open.
     * @param array<int, list<string>>                                                 $indexing The fields still being indexed, by model id.
     * @param array<string, string>                                                    $skipped  Why each module that was left out was.
     *
     * @return list<array{tone: string, text: string, links: list<array{label: string, href: string}>}>
     */
    public static function items(array $models, array $indexing, array $skipped, bool $manageModels, bool $manageSettings): array
    {
        $items = [];

        if ($manageModels) {
            $items = array_merge($items, self::indexing($models, $indexing), self::unnamed($models));
        }

        if ($manageSettings) {
            foreach ($skipped as $module => $why) {
                $items[] = [
                    'tone'  => 'bad',
                    'text'  => lang('Dashboard.attModule', ['name' => $module, 'why' => $why]),
                    'links' => [['label' => lang('Dashboard.attSettings'), 'href' => base_url('admin/settings')]],
                ];
            }
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $models
     * @param array<int, list<string>>   $indexing
     *
     * @return list<array{tone: string, text: string, links: list<array{label: string, href: string}>}>
     */
    private static function indexing(array $models, array $indexing): array
    {
        $links = [];
        foreach ($models as $model) {
            if (($indexing[(int) $model['id']] ?? []) !== []) {
                $links[] = ['label' => (string) $model['name'], 'href' => base_url('admin/models/' . (int) $model['id'] . '/edit')];
            }
        }

        return $links === [] ? [] : [[
            'tone'  => 'warn',
            'text'  => lang('Dashboard.attIndexing', ['n' => count($links)]),
            'links' => array_slice($links, 0, self::MAX_LINKS),
        ]];
    }

    /**
     * Models that have fields and no label field, so their lists and pickers can only show a number.
     *
     * @param list<array<string, mixed>> $models
     *
     * @return list<array{tone: string, text: string, links: list<array{label: string, href: string}>}>
     */
    private static function unnamed(array $models): array
    {
        $links = [];
        foreach ($models as $model) {
            $fields = is_string($model['fields'] ?? null) ? json_decode($model['fields'], true) : ($model['fields'] ?? null);
            $named  = is_string($model['label_field'] ?? null) && $model['label_field'] !== '';
            if (! $named && is_array($fields) && $fields !== []) {
                $links[] = ['label' => (string) $model['name'], 'href' => base_url('admin/models/' . (int) $model['id'] . '/edit')];
            }
        }

        return $links === [] ? [] : [[
            'tone'  => 'warn',
            'text'  => lang('Dashboard.attUnnamed', ['n' => count($links)]),
            'links' => array_slice($links, 0, self::MAX_LINKS),
        ]];
    }
}
