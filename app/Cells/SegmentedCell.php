<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A short row of exclusive choices that are links, such as Live and Trash. Each can show a count.
 */
class SegmentedCell extends Cell
{
    /**
     * @var list<array{label: string, href: string, current?: bool, count?: int|string, attrs?: array<string, string|bool>}>
     */
    public array $items = [];

    public string $label = '';

    protected string $view = 'segmented';
}
