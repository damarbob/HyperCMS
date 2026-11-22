<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A data table with sortable headings, an optional column of checkboxes, and a caption for screen readers.
 *
 * The cells of a row are HTML that the caller has already made safe, which is what a field type's `cell()` returns. A
 * heading is a link, so sorting works without scripts, and `attrs` adds the `hx-` attributes of htmx to those links.
 */
class TableCell extends Cell
{
    /**
     * A column is its `key`, its `label`, an optional `class` (`num` for numbers, `label-cell` for the entry's name), and
     * `sort` with the next sort address and the current direction.
     *
     * @var list<array{key: string, label: string, class?: string, href?: string, dir?: 'asc'|'desc'|null}>
     */
    public array $columns = [];

    /**
     * A row is its `cells` by column key as HTML, its `id` for the checkbox and its `label` for that checkbox.
     *
     * @var list<array{id?: int|string, label?: string, cells: array<string, string>, actions?: string}>
     */
    public array $rows = [];

    public string $caption = '';

    public bool $selectable = false;

    /**
     * @var array<string, string|bool>
     */
    public array $attrs = [];

    protected string $view = 'table';

    public function ariaSort(array $column): string
    {
        return match ($column['dir'] ?? null) {
            'asc'   => ' aria-sort="ascending"',
            'desc'  => ' aria-sort="descending"',
            default => '',
        };
    }

    public function headLink(array $column): string
    {
        $text = esc($column['label']);
        if (empty($column['href'])) {
            return $text;
        }

        $attributes = ['href' => $column['href']] + $this->attrs;
        if ($this->attrs !== []) {
            $attributes['hx-get'] = $column['href'];
        }
        $arrow = match ($column['dir'] ?? null) {
            'asc'   => icon('up', 'sm'),
            'desc'  => icon('down', 'sm'),
            default => '',
        };

        return '<a' . Html::attrs($attributes) . '>' . $text . $arrow . '</a>';
    }
}
