<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The search box and filters above a list, with the filters that are applied shown as chips that remove themselves.
 *
 * The bar is a plain form that sends a GET, so it works without scripts. With a `target` it also asks htmx for the new
 * list as the person types or changes a filter, and swaps it into the target.
 */
class FilterBarCell extends Cell
{
    public string $action = '';

    /** The label of the search box for screen readers and its placeholder. */
    public string $searchLabel = '';

    public string $q = '';

    /** The controls for the filters, as HTML the caller has already made safe. */
    public string $fields = '';

    /** The selector the list is swapped into, such as `#rows`. Empty means a normal page load. */
    public string $target = '';

    /**
     * The filters that are applied, as the parameters of a chip each, with `removeHref` that drops that filter.
     *
     * @var list<array<string, mixed>>
     */
    public array $applied = [];

    /** A line on the right of the applied filters, such as the sort. */
    public string $note = '';

    protected string $view = 'filter_bar';

    public function formAttributes(): string
    {
        $attributes = ['action' => $this->action, 'method' => 'get', 'role' => 'search'];
        if ($this->target !== '') {
            $attributes += [
                'hx-get'      => $this->action,
                'hx-target'   => $this->target,
                'hx-swap'     => 'outerHTML',
                'hx-push-url' => 'true',
                'hx-trigger'  => 'input changed delay:300ms from:find input[type=search], change',
            ];
        }

        return Html::attrs($attributes);
    }
}
