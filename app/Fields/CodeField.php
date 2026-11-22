<?php

namespace App\Fields;

/**
 * Source text in a `language` such as `html`, written in the code editor.
 */
final class CodeField extends LongTextField
{
    public function name(): string
    {
        return 'code';
    }

    protected function legacyOptions(array $old): array
    {
        $language = $old['data']['language'] ?? null;

        return ['language' => is_string($language) && $language !== '' ? $language : 'plaintext'];
    }

    protected function show(mixed $value, array $definition): string
    {
        return '<code class="mono">' . esc(self::excerpt((string) $value, 40)) . '</code>';
    }

    protected function island(array $definition): array
    {
        return ['data-island' => 'code', 'data-language' => is_string($definition['language'] ?? null) ? $definition['language'] : 'plaintext', 'spellcheck' => 'false'];
    }

    protected function rows(): int
    {
        return 12;
    }
}
