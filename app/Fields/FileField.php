<?php

namespace App\Fields;

/**
 * A file of the media library, or several when `multiple` is on, held as the id of the file. `accept` narrows what
 * the picker offers to `image`, `video`, `audio` or `document`, and is `any` by default.
 *
 * A value read from the old format is the path or link of the file as text until the files are moved into the
 * library. Until then `valueFromLegacy` returns the text, one item or a list of them, and an id stays an id.
 */
final class FileField extends IdField
{
    public const ACCEPT = ['any', 'image', 'video', 'audio', 'document'];

    public function name(): string
    {
        return 'file';
    }

    public function kind(array $definition): ?string
    {
        return null;
    }

    public function fromLegacy(array $old, array $context = []): array
    {
        return $this->build($old, ['multiple' => self::flag($old['multiple'] ?? false), 'accept' => 'any']);
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        $multiple = self::flag($definition['multiple'] ?? false);
        $items    = $old === null || $old === '' ? [] : (is_array($old) && array_is_list($old) ? $old : [$old]);

        foreach ($items as $item) {
            if (! is_int($item) && (! is_string($item) || $item === '')) {
                throw new ConversionProblem('the file value "' . self::shown($item) . '" is not a path or a link');
            }
        }

        return $multiple ? $items : ($items[0] ?? null);
    }
}
