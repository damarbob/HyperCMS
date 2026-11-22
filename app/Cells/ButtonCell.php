<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

/**
 * A button, or a link that looks like one when it has an `href`.
 *
 * An icon-only button has no visible text, so its `label` is written as the accessible name.
 */
class ButtonCell extends Cell
{
    public string $label = '';

    /** The address of a link. Empty makes a real button. */
    public string $href = '';

    /** `button`, `submit` or `reset`, for a real button. */
    public string $type = 'button';

    /** `default`, `primary`, `danger`, `solid-danger` or `quiet`. */
    public string $variant = 'default';

    /** `md` or `small`. */
    public string $size = 'md';

    /** The name of an icon from the sprite. */
    public string $icon = '';

    public bool $iconOnly = false;

    public bool $disabled = false;

    /**
     * Extra attributes, such as the `hx-` attributes of htmx.
     *
     * @var array<string, string|bool>
     */
    public array $attrs = [];

    protected string $view = 'button';

    public function classes(): string
    {
        return Html::classes([
            'btn',
            $this->variant === 'primary' ? 'primary' : null,
            in_array($this->variant, ['danger', 'solid-danger'], true) ? 'danger' : null,
            $this->variant === 'solid-danger' ? 'solid' : null,
            $this->variant === 'quiet' ? 'quiet' : null,
            $this->size === 'small' ? 'small' : null,
            $this->iconOnly ? 'icon-only' : null,
        ]);
    }

    public function attributes(): string
    {
        $attributes = $this->attrs;
        if ($this->iconOnly) {
            $attributes['aria-label'] = $this->label;
            $attributes['title']      = $this->label;
        }
        if ($this->href === '') {
            $attributes['type'] = in_array($this->type, ['button', 'submit', 'reset'], true) ? $this->type : 'button';
            if ($this->disabled) {
                $attributes['disabled'] = true;
            }
        } else {
            $attributes['href'] = $this->href;
            if ($this->disabled) {
                $attributes['aria-disabled'] = 'true';
            }
        }

        return Html::attrs($attributes);
    }
}
