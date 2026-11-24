<?php

namespace App\Modules\Slots;

use InvalidArgumentException;
use Throwable;

/**
 * The payload of the slot on the dashboard. A module adds panels, each one a title and a view of its own. Core draws the
 * frame and the heading, asks the ability before the panel is even kept, and leaves out a panel whose view fails, so one
 * module cannot take the dashboard down.
 */
final class DashboardPanels
{
    public const MAIN = 'main';

    public const SIDE = 'side';

    /**
     * @var list<array{title: string, view: string, data: array<string, mixed>, column: string, href: string, linkLabel: string}>
     */
    private array $panels = [];

    /**
     * @param \Closure(string): bool $may Answers whether the person holds an ability.
     */
    public function __construct(private readonly \Closure $may)
    {
    }

    /**
     * Add a panel.
     *
     * @param string               $view      The view that draws the inside of the panel. It is drawn only if the panel is shown.
     * @param array<string, mixed> $data      What the view needs.
     * @param string               $ability   The panel is kept only for people who hold it. Empty means everyone who may open the dashboard.
     * @param string               $column    `main` for the wide column beside the recent work, `side` for the narrow one.
     * @param string               $href      An address for a quiet button in the heading, such as the page that shows more.
     * @param string               $linkLabel The text of that button.
     *
     * @throws InvalidArgumentException When the title or the view is empty, or the column is not one of the two.
     */
    public function panel(string $title, string $view, array $data = [], string $ability = '', string $column = self::SIDE, string $href = '', string $linkLabel = ''): void
    {
        if (trim($title) === '' || trim($view) === '') {
            throw new InvalidArgumentException('A dashboard panel needs a title and a view.');
        }
        if (! in_array($column, [self::MAIN, self::SIDE], true)) {
            throw new InvalidArgumentException("There is no dashboard column '$column'.");
        }
        if ($ability !== '' && ! ($this->may)($ability)) {
            return;
        }

        $this->panels[] = ['title' => $title, 'view' => $view, 'data' => $data, 'column' => $column, 'href' => $href, 'linkLabel' => $linkLabel];
    }

    /**
     * How many panels a column holds.
     */
    public function count(string $column): int
    {
        return count(array_filter($this->panels, static fn (array $panel): bool => $panel['column'] === $column));
    }

    /**
     * The panels of one column as HTML, in the order the modules added them.
     */
    public function html(string $column): string
    {
        $html = '';
        foreach ($this->panels as $index => $panel) {
            if ($panel['column'] !== $column) {
                continue;
            }

            $level = ob_get_level();

            try {
                $body = view($panel['view'], $panel['data']);
            } catch (Throwable $e) {
                // A view that failed halfway leaves its output buffer open.
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                log_message('error', 'The dashboard panel "{title}" was left out. {error}', ['title' => $panel['title'], 'error' => $e->getMessage()]);

                continue;
            }

            $id   = 'dp-' . $index;
            $link = $panel['href'] !== '' && $panel['linkLabel'] !== ''
                ? ui('button', ['label' => $panel['linkLabel'], 'href' => $panel['href'], 'variant' => 'quiet', 'size' => 'small'])
                : '';

            $html .= '<section class="panel" aria-labelledby="' . esc($id, 'attr') . '"><header><h2 id="' . esc($id, 'attr') . '">' . esc($panel['title']) . '</h2>' . $link . '</header>' . $body . '</section>';
        }

        return $html;
    }
}
