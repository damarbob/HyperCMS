<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A dialog made on the browser's own `dialog` element, which traps focus and closes on Escape by itself.
 *
 * The page opens it with `document.getElementById(id).showModal()`. The confirm dialog of the layout is this cell with
 * the `data-confirm` hooks that `ui.js` uses to answer `hx-confirm`.
 */
class ModalCell extends Cell
{
    public string $id = '';

    public string $title = '';

    /** The body, as HTML the caller has already made safe. */
    public string $body = '';

    public string $confirmLabel = '';

    public string $cancelLabel = '';

    /** Whether the confirming action is destructive, which makes its button red. */
    public bool $danger = false;

    protected string $view = 'modal';
}
