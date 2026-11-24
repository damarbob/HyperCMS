<?php
// hyper_ui_helper.php
// What the pages of the new admin use to draw icons and the parts of the design system.

if (! function_exists('icon')) {
    /**
     * An inline icon from the sprite in `public/assets/ui/icons.svg`.
     *
     * @param string $name  The icon, for example `plus`. An unknown or odd name draws the `doc` icon.
     * @param string $class Extra classes, for example `sm`.
     */
    function icon(string $name, string $class = ''): string
    {
        $name  = preg_match('/^[a-z0-9-]+$/', $name) === 1 ? $name : 'doc';
        $class = trim('icon ' . $class);

        return '<svg class="' . attr($class) . '" aria-hidden="true" focusable="false"><use href="' . attr((string) parse_url(base_url('assets/ui/icons.svg'), PHP_URL_PATH)) . '#' . $name . '"/></svg>';
    }
}

if (! function_exists('attr')) {
    /**
     * Escape a value for an attribute written between double quotes.
     */
    function attr(?string $value): string
    {
        return App\Cells\Html::attr((string) $value);
    }
}

if (! function_exists('attrs')) {
    /**
     * Write an array as attributes with escaped values. See `App\Cells\Html::attrs()`.
     *
     * @param array<string, mixed> $attributes
     */
    function attrs(array $attributes): string
    {
        return App\Cells\Html::attrs($attributes);
    }
}

if (! function_exists('admin_theme')) {
    /**
     * The colour scheme to draw the page in, `light`, `dark` or `system`. The signed-in user's own choice comes first,
     * then the cookie the sign in page uses, and without either the page follows the system.
     */
    function admin_theme(): string
    {
        $choice = null;

        // While the site is being installed there is no database, so nobody is signed in and nothing may ask for one.
        $user = \App\Libraries\Install\InstallState::current()->open() ? null : auth()->user();
        if ($user !== null) {
            $choice = setting()->get('Hyper.theme', 'user:' . $user->id);
        }
        $choice ??= service('request')->getCookie('hyper_theme');

        return in_array($choice, ['light', 'dark'], true) ? $choice : 'system';
    }
}

if (! function_exists('ui')) {
    /**
     * Draw one part of the design system, for example `ui('button', ['label' => 'Save', 'variant' => 'primary'])`.
     *
     * The parts are the view cells in `app/Cells`, named by the class without `Cell`, in camel case.
     *
     * @param array<string, mixed> $params The public properties of the cell.
     */
    function ui(string $name, array $params = []): string
    {
        return view_cell('App\\Cells\\' . ucfirst($name) . 'Cell', $params);
    }
}
