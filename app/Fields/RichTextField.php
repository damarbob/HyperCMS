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
}
