<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * What a list shows when it has nothing to show. It says what is missing and offers the next step.
 */
class EmptyStateCell extends Cell
{
    public string $icon = 'inbox';

    public string $title = '';

    public string $text = '';

    /** The label and address of the one action, for example Create the first entry. */
    public string $actionLabel = '';

    public string $actionHref = '';

    protected string $view = 'empty_state';
}
