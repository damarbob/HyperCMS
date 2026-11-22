<?php

namespace App\Fields;

/**
 * HTML written in the rich text editor.
 */
final class RichTextField extends LongTextField
{
    public function name(): string
    {
        return 'richtext';
    }

    protected function plain(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function island(array $definition): array
    {
        return ['data-island' => 'richtext'];
    }

    protected function rows(): int
    {
        return 10;
    }
}
