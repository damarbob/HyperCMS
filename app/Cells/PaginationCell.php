<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The page links under a list, with a line that says which entries are shown.
 *
 * The links are real addresses, so they work without scripts. When `attrs` holds the `hx-` attributes of htmx, each link
 * also asks for its page and swaps it in.
 */
class PaginationCell extends Cell
{
    public int $page = 1;

    public int $perPage = 25;

    public int $total = 0;

    /** The address of a page, with `{page}` where the number goes. */
    public string $href = '?page={page}';

    /**
     * Attributes for every link, for example `hx-target` and `hx-push-url`. `hx-get` is added from the address.
     *
     * @var array<string, string|bool>
     */
    public array $attrs = [];

    protected string $view = 'pagination';

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function current(): int
    {
        return min(max(1, $this->page), $this->pageCount());
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : ($this->current() - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->current() * $this->perPage);
    }

    /**
     * The page numbers to draw, with null where pages are left out. The first, the last and the neighbours of the
     * current page are always drawn.
     *
     * @return list<int|null>
     */
    public function window(): array
    {
        $last    = $this->pageCount();
        $current = $this->current();
        $shown   = array_values(array_unique(array_filter([1, $current - 1, $current, $current + 1, $last], static fn (int $p): bool => $p >= 1 && $p <= $last)));
        sort($shown);

        $out = [];
        foreach ($shown as $i => $number) {
            if ($i > 0 && $number - $shown[$i - 1] > 1) {
                $out[] = null;
            }
            $out[] = $number;
        }

        return $out;
    }

    public function link(int $page, string $label, bool $current = false): string
    {
        $href       = str_replace('{page}', (string) $page, $this->href);
        $attributes = ['href' => $href] + $this->attrs;
        if ($this->attrs !== []) {
            $attributes['hx-get'] = $href;
        }
        if ($current) {
            $attributes['aria-current'] = 'page';
        }

        return '<a' . Html::attrs($attributes) . '>' . esc($label) . '</a>';
    }
}
