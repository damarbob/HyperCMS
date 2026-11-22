<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * Tabs that are links to other pages or to other parts of the same page. The current one is marked for screen readers.
 */
class TabsCell extends Cell
{
    /**
     * @var list<array{label: string, href: string, current?: bool}>
     */
    public array $items = [];

    /** The name of the group, for screen readers. */
    public string $label = '';

    protected string $view = 'tabs';
}
