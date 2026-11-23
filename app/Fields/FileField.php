<?php

namespace App\Fields;

use App\Data\Files;

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
        $items = array_values(array_filter(is_array($value) ? $value : [$value], static fn (mixed $item): bool => $item !== null && $item !== ''));
        $files = service('files')->findMany(array_filter($items, 'is_int'));

        $chips = [];
        foreach ($items as $item) {
            $file = is_int($item) ? ($files[$item] ?? null) : null;
            if ($file !== null) {
                $chips[] = '<a class="chip" href="' . attr(Files::url($file)) . '" target="_blank" rel="noopener noreferrer">' . esc((string) $file['name']) . '</a>';
            } else {
                // A file that was deleted from the library, or a value the upgrade could not turn into a file.
                $chips[] = '<span class="chip warn">' . esc(is_int($item) ? lang('Fields.fileMissing', ['id' => $item]) : self::shown($item)) . '</span>';
            }
        }

        return implode(' ', $chips);
    }

    /**
     * The files are held by a hidden `select` of their ids, which is what the form posts. With the media library script
     * the select is drawn as chips and a button that opens the library, and a person without the right to use the library
     * sees the chosen files and keeps them when they save.
     */
    public function input(mixed $value, array $definition, ?string $error = null): string
    {
        $many  = self::flag($definition['multiple'] ?? false);
        $items = array_values(array_filter(is_array($value) ? $value : [$value], static fn (mixed $item): bool => $item !== null && $item !== ''));
        $files = service('files')->findMany(array_filter($items, 'is_int'));

        $options = $many || self::flag($definition['required'] ?? false) ? '' : '<option value="">' . esc(lang('Fields.chooseOne')) . '</option>';
        foreach ($items as $item) {
            $file = is_int($item) ? ($files[$item] ?? null) : null;
            if ($file !== null) {
                $options .= '<option value="' . (int) $item . '" selected data-url="' . attr(Files::url($file)) . '">' . esc((string) $file['name']) . '</option>';
            } else {
                $options .= '<option value="' . attr(self::shown($item)) . '" selected>' . esc(is_int($item) ? lang('Fields.fileMissing', ['id' => $item]) : self::shown($item)) . '</option>';
            }
        }

        $canPick = can('media.manage');
        $extra   = $canPick ? [
            'data-media-field' => true,
            'data-accept'      => in_array($definition['accept'] ?? 'any', self::ACCEPT, true) ? (string) ($definition['accept'] ?? 'any') : 'any',
            'data-choose'      => lang('Fields.chooseFile'),
            'data-change'      => lang('Fields.changeFile'),
            'data-remove'      => lang('Fields.pickerRemove'),
            'data-title'       => lang('Fields.chooseFileTitle'),
        ] : ['hidden' => true];

        // The browser's own check cannot focus a hidden select, so the server's check of a required field is what answers.
        $attributes = ['required' => false] + self::inputAttributes($definition, $error, $many) + ['multiple' => $many] + $extra;
        $attributes['required'] = false;

        $select = '<select class="select"' . attrs($attributes) . '>' . $options . '</select>';
        if (! $canPick) {
            $select = '<div class="chips">' . ($items === [] ? '<span class="muted">' . esc(lang('Fields.noFiles')) . '</span>' : $this->show($items, $definition)) . '</div>' . $select;
        }

        return $this->field($definition, $select, $error);
    }

    /**
     * A file must be in the library, so an entry cannot keep the id of a file that was deleted.
     */
    protected function check(mixed $value, array $definition): ?string
    {
        $error = parent::check($value, $definition);
        if ($error !== null) {
            return $error;
        }

        $ids   = self::flag($definition['multiple'] ?? false) ? $value : [$value];
        $found = service('files')->findMany($ids);
        foreach ($ids as $id) {
            if (! isset($found[$id])) {
                return lang('Fields.fileGone', ['field' => self::labelOf($definition), 'id' => $id]);
            }
        }

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
