<?php

namespace App\Modules;

/**
 * An item a module adds to the admin menu. `section` is the heading the item goes under, `icon` is the name of an icon
 * of the design system, and `ability` limits who sees the item.
 */
final class MenuEntry
{
    /**
     * @param string $key     A name for the item that no other item uses, such as `user-management`.
     * @param string $section The heading, such as `Admin.others`'s text. Empty puts the item under the settings.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $url,
        public readonly string $icon = 'doc',
        public readonly ?string $ability = null,
        public readonly string $section = '',
        public readonly string $hint = '',
    ) {
    }

    /**
     * The item as the menu of the admin holds it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $item = ['url' => $this->url, 'icon' => $this->icon, 'text' => $this->label, 'tooltip_content' => $this->label, 'tooltip_placement' => 'right'];
        if ($this->hint !== '') {
            $item['hint'] = $this->hint;
        }
        if ($this->ability !== null) {
            $item['ability'] = $this->ability;
        }

        return $item;
    }
}
