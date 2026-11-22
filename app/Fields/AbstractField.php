<?php

namespace App\Fields;

use App\Data\Entries\FieldRules;

/**
 * What every field type shares: the keys all definitions carry, the empty and required rule, and the reading of the
 * keys common to the old format.
 */
abstract class AbstractField implements FieldType
{
    public function normalise(mixed $value, array $definition): mixed
    {
        return $value;
    }

    public function validate(mixed $value, array $definition): ?string
    {
        if ($this->isEmpty($value)) {
            return self::flag($definition['required'] ?? false)
                ? lang('Fields.required', ['field' => self::labelOf($definition)])
                : null;
        }

        return $this->check($value, $definition);
    }

    public function kind(array $definition): ?string
    {
        return null;
    }

    public function sortKey(mixed $value): string|float|null
    {
        return null;
    }

    public function valueFromLegacy(mixed $old, array $definition): mixed
    {
        return $old;
    }

    public function cell(mixed $value, array $definition): string
    {
        return $this->isEmpty($value) ? self::notSet() : $this->show($value, $definition);
    }

    public function filters(array $definition): array
    {
        return [];
    }

    /**
     * The HTML of a value that is not empty. The default shows it as text.
     *
     * @param array<string, mixed> $definition
     */
    protected function show(mixed $value, array $definition): string
    {
        return esc(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    /**
     * The whole field of the entry form around an input the type has drawn.
     *
     * @param array<string, mixed> $definition
     * @param bool                 $group      True for several inputs that share one label, such as radio buttons.
     */
    protected function field(array $definition, string $control, ?string $error, bool $group = false): string
    {
        return ui('field', [
            'id'       => self::domId($definition),
            'label'    => self::labelOf($definition),
            'required' => self::flag($definition['required'] ?? false),
            'help'     => self::help($definition),
            'error'    => $error ?? '',
            'input'    => $control,
            'group'    => $group,
        ]);
    }

    /**
     * The attributes every input of a field shares: its id, the name it posts under, and what points it at the help and
     * the error.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<string, string|bool|null>
     */
    protected static function inputAttributes(array $definition, ?string $error, bool $many = false): array
    {
        $id      = self::domId($definition);
        $describe = trim((self::help($definition) !== '' ? $id . '-help ' : '') . ($error !== null && $error !== '' ? $id . '-error' : ''));

        return [
            'id'               => $id,
            'name'             => 'fields[' . (string) ($definition['id'] ?? '') . ']' . ($many ? '[]' : ''),
            'required'         => self::flag($definition['required'] ?? false),
            'aria-invalid'     => $error !== null && $error !== '' ? 'true' : null,
            'aria-describedby' => $describe !== '' ? $describe : null,
        ];
    }

    /**
     * An id for the page that is safe in an attribute and in a selector, whatever the field id holds.
     *
     * @param array<string, mixed> $definition
     */
    protected static function domId(array $definition): string
    {
        return 'f-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($definition['id'] ?? ''));
    }

    /**
     * The help line of a field. It may hold `[base_url]`, which the old form replaced by the address of the site.
     *
     * @param array<string, mixed> $definition
     */
    protected static function help(array $definition): string
    {
        $help = $definition['help'] ?? '';

        return is_string($help) ? str_replace('[base_url]', base_url(), $help) : '';
    }

    protected static function notSet(): string
    {
        return '<span class="muted">' . esc(lang('Fields.notSet')) . '</span>';
    }

    /**
     * Shorten a text for a list, on a word of the text when it can.
     */
    protected static function excerpt(string $text, int $length = 80): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }

    /**
     * Check a value that is not empty. The default accepts everything.
     *
     * @param array<string, mixed> $definition
     */
    protected function check(mixed $value, array $definition): ?string
    {
        return null;
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * The definition a field of this type gets in version 2. The type's own options come first, then the keys
     * every type shares, and a shared key that is off or empty is left out.
     *
     * @param array<string, mixed> $old     The field in the old format.
     * @param array<string, mixed> $options The options of this type.
     * @param mixed                $default The default value, or null for none.
     *
     * @return array<string, mixed>
     */
    protected function build(array $old, array $options = [], mixed $default = null): array
    {
        $id = (string) ($old['id'] ?? '');
        if ($id === '') {
            throw new ConversionProblem('a field has no id');
        }

        $definition = ['id' => $id, 'label' => (string) ($old['label'] ?? $id), 'type' => $this->name()] + $options;

        if (self::flag($old['required'] ?? false)) {
            $definition['required'] = true;
        }
        if (isset($old['helper']) && is_string($old['helper']) && $old['helper'] !== '') {
            $definition['help'] = $old['helper'];
        }
        if ($default !== null && $default !== '' && $default !== []) {
            $definition['default'] = $default;
        }
        if (self::flag($old['filterable'] ?? false)) {
            $definition['filterable'] = true;
        }

        return $definition;
    }

    /**
     * Whether a value in a definition means on. The old format wrote some of these as text.
     */
    public static function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /**
     * The name of a field as a message shows it.
     *
     * @param array<string, mixed> $definition
     */
    public static function labelOf(array $definition): string
    {
        $label = $definition['label'] ?? $definition['id'] ?? '';

        return is_scalar($label) ? (string) $label : '';
    }

    /**
     * A number written as text, as a number, or null when the text is not a number. A whole number that fits an
     * int is an int, so `"12"` is `12` and `"12.50"` is `12.5`.
     */
    public static function numberFrom(mixed $value): int|float|null
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);
        if (preg_match('/^-?(0|[1-9][0-9]*)$/', $text) === 1 && (string) (int) $text === $text) {
            return (int) $text;
        }
        if (is_numeric($text) && is_finite((float) $text)) {
            return (float) $text;
        }

        return null;
    }

    /**
     * A shortened copy of a value for a message.
     */
    protected static function shown(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : get_debug_type($value);

        return mb_strlen($text) > 40 ? mb_substr($text, 0, 40) . '...' : $text;
    }

    /**
     * The sort key of a text, the one the lists use today.
     */
    protected static function textKey(mixed $value): ?string
    {
        return is_scalar($value) ? FieldRules::sortFold((string) $value) : null;
    }
}
