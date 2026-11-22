<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * The corner where messages appear. Messages the server queued in the session show first, and later ones arrive from
 * scripts and from the `HX-Trigger` header. The region is a polite live region, so a screen reader reads each message.
 */
class ToastsCell extends Cell
{
    /**
     * The messages to show on load, each with a `type` of `ok`, `bad` or `warn` and a `message`.
     *
     * @var list<array{type: string, message: string}>
     */
    public array $initial = [];

    protected string $view = 'toasts';

    public function initialJson(): string
    {
        $clean = [];
        foreach ($this->initial as $item) {
            $type    = in_array($item['type'] ?? '', ['ok', 'bad', 'warn'], true) ? $item['type'] : 'ok';
            $clean[] = ['type' => $type, 'message' => (string) ($item['message'] ?? '')];
        }

        return Html::attr(json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }
}
