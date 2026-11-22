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
}
