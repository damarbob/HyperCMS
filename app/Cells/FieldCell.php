<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A form field, which is a label, an input, a help line and an error line wired together for screen readers.
 *
 * The cell draws the input itself for the simple kinds (`text`, `email`, `password`, `number`, `search`, `textarea` and
 * `select`). For anything else the caller passes the input as HTML in `input`, and uses `describedBy()` to point the
 * input at the help and error lines.
 */
class FieldCell extends Cell
{
    public string $id = '';

    public string $name = '';

    public string $label = '';

    /** The input's kind. */
    public string $kind = 'text';

    public string $value = '';

    public string $help = '';

    public string $error = '';

    public bool $required = false;

    public string $placeholder = '';

    public string $autocomplete = '';

    /**
     * The choices of a `select`, as value to label.
     *
     * @var array<string, string>
     */
    public array $options = [];

    /** Input drawn by the caller, as HTML the caller has already made safe. */
    public string $input = '';

    /**
     * Extra attributes for the input.
     *
     * @var array<string, string|bool>
     */
    public array $attrs = [];

    protected string $view = 'field';

    public function describedBy(): string
    {
        return trim(($this->help !== '' ? $this->id . '-help ' : '') . ($this->error !== '' ? $this->id . '-error' : ''));
    }

    public function control(): string
    {
        if ($this->input !== '') {
            return $this->input;
        }

        $common = [
            'id'               => $this->id,
            'name'             => $this->name !== '' ? $this->name : $this->id,
            'required'         => $this->required,
            'aria-invalid'     => $this->error !== '' ? 'true' : null,
            'aria-describedby' => $this->describedBy() !== '' ? $this->describedBy() : null,
            'placeholder'      => $this->placeholder !== '' ? $this->placeholder : null,
            'autocomplete'     => $this->autocomplete !== '' ? $this->autocomplete : null,
        ] + $this->attrs;

        if ($this->kind === 'textarea') {
            return '<textarea class="textarea"' . Html::attrs($common) . '>' . esc($this->value) . '</textarea>';
        }

        if ($this->kind === 'select') {
            $options = '';
            foreach ($this->options as $value => $text) {
                $options .= '<option value="' . Html::attr((string) $value) . '"' . ((string) $value === $this->value ? ' selected' : '') . '>' . esc($text) . '</option>';
            }

            return '<select class="select"' . Html::attrs($common) . '>' . $options . '</select>';
        }

        $type = in_array($this->kind, ['text', 'email', 'password', 'number', 'search', 'url', 'tel'], true) ? $this->kind : 'text';

        return '<input class="input" type="' . $type . '" value="' . Html::attr($this->value) . '"' . Html::attrs($common) . '>';
    }
}
