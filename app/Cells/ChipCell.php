<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A small pill of text, such as a tag, a status or an applied filter.
 */
class ChipCell extends Cell
{
    public string $label = '';

    /** `neutral`, `ok`, `warn`, `bad` or `tint`. */
    public string $tone = 'neutral';

    /** The address that removes this chip, for a filter. Empty draws no remove button. */
    public string $removeHref = '';

    public string $removeLabel = '';

    /**
     * Extra attributes for the remove link, such as the `hx-` attributes of htmx.
     *
     * @var array<string, string|bool>
     */
    public array $removeAttrs = [];

    protected string $view = 'chip';

    public function classes(): string
    {
        return Html::classes(['chip', in_array($this->tone, ['ok', 'warn', 'bad', 'tint'], true) ? $this->tone : null]);
    }
}
