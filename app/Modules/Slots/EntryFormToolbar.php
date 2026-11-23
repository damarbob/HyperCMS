<?php

namespace App\Modules\Slots;

/**
 * The payload of the slot beside the save button of the entry form. A module adds buttons, as a link with a label.
 */
final class EntryFormToolbar
{
    /**
     * @var list<array{label: string, href: string, icon: string}>
     */
    private array $buttons = [];

    /**
     * @param int $modelId The model of the entry.
     * @param int $entryId The entry, or 0 for a new one.
     */
    public function __construct(public readonly int $modelId, public readonly int $entryId)
    {
    }

    public function isNew(): bool
    {
        return $this->entryId === 0;
    }

    /**
     * Add a button that opens an address.
     */
    public function link(string $label, string $href, string $icon = 'external'): void
    {
        $this->buttons[] = ['label' => $label, 'href' => $href, 'icon' => $icon];
    }

    /**
     * The buttons as HTML the design system draws, already escaped.
     */
    public function html(): string
    {
        $html = '';
        foreach ($this->buttons as $button) {
            $html .= ui('button', ['label' => $button['label'], 'href' => $button['href'], 'icon' => $button['icon']]);
        }

        return $html;
    }
}
