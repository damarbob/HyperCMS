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

    public function settings(): array
    {
        return [
            ['key' => 'multiple', 'control' => 'toggle', 'label' => lang('Fields.setMultipleFiles'), 'default' => false],
            ['key' => 'accept', 'control' => 'select', 'label' => lang('Fields.setAccept'), 'default' => 'any', 'choices' => ['any' => lang('Fields.acceptAny'), 'image' => lang('Fields.acceptImage'), 'video' => lang('Fields.acceptVideo'), 'audio' => lang('Fields.acceptAudio'), 'document' => lang('Fields.acceptDocument')]],
        ];
    }

    public function kind(array $definition): ?string
    {
        return null;
    }

    protected function show(mixed $value, array $definition): string
    {
        $links = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            $links[] = $this->link($item);
        }

        return implode(' ', array_filter($links));
    }

    /**
     * One stored file as a link. Until the media library holds the files, a value is the path or the link of the file.
     */
    private function link(mixed $item): string
    {
        if (is_int($item)) {
            return '<span class="chip">' . esc(lang('Fields.fileNumber', ['id' => $item])) . '</span>';
        }
        if (! is_string($item) || $item === '') {
            return '';
        }

        $href = preg_match('#^(https?:)?//#i', $item) === 1 ? $item : base_url(ltrim(str_replace('\\', '/', $item), '/'));
        $name = basename((string) parse_url($item, PHP_URL_PATH));

        return '<a class="chip" href="' . attr($href) . '" target="_blank" rel="noopener noreferrer">' . esc($name !== '' ? $name : $item) . '</a>';
    }

    /**
     * Files cannot be added or changed until the media library exists. The stored ones are shown, and a save keeps them.
     */
    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $shown   = $this->isEmpty($value) ? '<span class="muted">' . esc(lang('Fields.noFiles')) . '</span>' : '<div class="chips">' . $this->show($value, $definition) . '</div>';
        $control = '<div id="' . attr(self::domId($definition)) . '">' . $shown . '</div>';
        $note    = lang('Fields.filesManaged');

        return ui('field', [
            'id'       => self::domId($definition),
            'label'    => self::labelOf($definition),
            'required' => false,
            'help'     => $note,
            'error'    => $error ?? '',
            'input'    => $control,
            'group'    => true,
        ]);
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
