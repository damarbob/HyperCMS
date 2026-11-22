<?php
/**
 * The shell of the new admin: a rail on the canvas, a top bar, and the page on a rounded sheet.
 *
 * A page extends it with `$this->extend('admin/layout/app')` and writes its body in the `content` section. It may set
 * `$crumbs`, a list of `[label, address]` pairs where the last one has no address, and `$title`.
 */

use App\Libraries\RailMenu;

$hyperConfig = config(\Config\Hyper::class);
$user        = auth()->user();
$theme       = admin_theme();
$version     = esc((string) $hyperConfig->appVersion, 'url');
$asset       = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);

$rail = RailMenu::prepare($menu ?? [], (string) parse_url(current_url(), PHP_URL_PATH));

// Messages the last request queued for this page become toasts.
$flash = [];
foreach (['success' => 'ok', 'info' => 'ok', 'warning' => 'warn', 'error' => 'bad'] as $key => $type) {
    $queued = session()->getFlashdata($key);
    foreach ($queued === null ? [] : (array) $queued as $text) {
        $flash[] = ['type' => $type, 'message' => (string) $text];
    }
}

$crumbs   = $crumbs ?? [];
$username = $user !== null ? (string) $user->username : '';
$initials = mb_strtoupper(mb_substr($username !== '' ? $username : '?', 0, 2));
?>
<!doctype html>
<html lang="<?= attr($locale ?? service('request')->getLocale()) ?>"<?= $theme !== 'system' ? ' data-theme="' . attr($theme) . '"' : '' ?>
    data-error-text="<?= attr(lang('Ui.requestFailed')) ?>" data-offline-text="<?= attr(lang('Ui.offline')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-header" content="<?= attr(csrf_header()) ?>">
    <meta name="csrf-token" content="<?= attr(csrf_hash()) ?>">
    <meta name="assets-base" content="<?= attr(base_url('assets/')) ?>">
    <meta name="site-base" content="<?= attr(base_url()) ?>">
    <title><?= esc($title ?? $hyperConfig->appName) ?></title>
    <link rel="stylesheet" href="<?= $asset('ui/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/components.css') ?>">
    <?= $this->renderSection('head') ?>
    <script src="<?= $asset('ui/ui.js') ?>" defer></script>
    <script src="<?= $asset('vendor/htmx.org/dist/htmx.min.js') ?>" defer></script>
    <script src="<?= $asset('vendor/alpinejs/dist/cdn.min.js') ?>" defer></script>
</head>

<body>
    <a class="skip" href="#content"><?= esc(lang('Ui.skipToContent')) ?></a>

    <div class="shell" x-data="shell" @keydown.escape.window="close()">
        <aside class="rail" id="rail" :class="{ open: menu }" aria-label="<?= attr(lang('Ui.mainNav')) ?>">
            <a class="brand" href="<?= attr(base_url('admin/dashboard')) ?>">
                <span class="logo"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.2 5.1 5.5.5-4.2 3.7 1.3 5.4L10 13.4l-4.8 2.8 1.3-5.4L2.3 7.1l5.5-.5z" /></svg></span>
                <?= esc($hyperConfig->appName) ?>
            </a>

            <nav>
                <?php foreach ($rail as $group) : ?>
                    <?php if ($group['label'] !== '') : ?><div class="group"><?= esc($group['label']) ?></div><?php endif ?>
                    <?php foreach ($group['items'] as $item) : ?>
                        <a href="<?= attr($item['url']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>>
                            <?= icon($item['icon']) ?><span><?= esc($item['text']) ?></span>
                            <?php if ($item['count'] !== null) : ?><span class="count"><?= esc((string) $item['count']) ?></span><?php endif ?>
                        </a>
                        <?php if ($item['open'] && $item['children'] !== []) : ?>
                            <?php foreach ($item['children'] as $child) : ?>
                                <a href="<?= attr($child['url']) ?>" style="padding-left: 34px"<?= $child['current'] ? ' aria-current="page"' : '' ?>><span><?= esc($child['text']) ?></span></a>
                            <?php endforeach ?>
                        <?php endif ?>
                    <?php endforeach ?>
                <?php endforeach ?>
            </nav>

            <div class="foot"><span class="pip"></span><?= esc(lang('Ui.version', ['version' => $hyperConfig->appVersion])) ?></div>
        </aside>
        <div class="scrim" :class="{ open: menu }" @click="close()"></div>

        <div class="main">
            <div class="progress" aria-hidden="true"></div>
            <header class="topbar">
                <button class="icon-btn menu-toggle" type="button" @click="toggle()" :aria-expanded="menu" aria-controls="rail" aria-label="<?= attr(lang('Ui.openMenu')) ?>"><?= icon('menu') ?></button>

                <?php if ($crumbs !== []) : ?>
                    <nav aria-label="<?= attr(lang('Ui.breadcrumb')) ?>">
                        <ol class="crumbs">
                            <?php foreach ($crumbs as [$label, $href]) : ?>
                                <li><?= $href !== null ? '<a href="' . attr($href) . '">' . esc($label) . '</a>' : '<span aria-current="page">' . esc($label) . '</span>' ?></li>
                            <?php endforeach ?>
                        </ol>
                    </nav>
                <?php endif ?>

                <div class="spacer"></div>
                <?= $this->renderSection('topbar') ?>

                <div class="theme" role="group" aria-label="<?= attr(lang('Ui.colourScheme')) ?>" x-data="theme('<?= attr($theme) ?>', '<?= attr(base_url('admin/theme')) ?>')">
                    <button type="button" :aria-pressed="choice === 'system'" @click="set('system')" aria-label="<?= attr(lang('Ui.followSystem')) ?>" title="<?= attr(lang('Ui.followSystem')) ?>"><?= icon('monitor') ?></button>
                    <button type="button" :aria-pressed="choice === 'light'" @click="set('light')" aria-label="<?= attr(lang('Ui.light')) ?>" title="<?= attr(lang('Ui.light')) ?>"><?= icon('sun') ?></button>
                    <button type="button" :aria-pressed="choice === 'dark'" @click="set('dark')" aria-label="<?= attr(lang('Ui.dark')) ?>" title="<?= attr(lang('Ui.dark')) ?>"><?= icon('moon') ?></button>
                </div>

                <?php if ($user !== null) : ?>
                    <div class="who" style="position: relative" x-data="menu" @keydown.escape="close()" @click.outside="close()">
                        <button class="icon-btn" type="button" style="width: auto; padding: 0 4px; gap: 8px; display: inline-flex; align-items: center" aria-haspopup="menu" :aria-expanded="open" @click="toggle()" aria-label="<?= attr(lang('Ui.account')) ?>">
                            <span class="avatar" aria-hidden="true"><?= esc($initials) ?></span><span><?= esc($username) ?></span>
                        </button>
                        <div class="menu" role="menu" x-ref="list" x-show="open" x-cloak style="right: 0; top: 40px" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)">
                            <a role="menuitem" href="<?= attr(base_url('admin/profile')) ?>"><?= icon('user') ?><?= esc(lang('Ui.profile')) ?></a>
                            <hr>
                            <a role="menuitem" href="<?= attr(url_to('logout')) ?>"><?= icon('logout') ?><?= esc(lang('Ui.signOut')) ?></a>
                        </div>
                    </div>
                <?php endif ?>
            </header>

            <main class="page" id="content" tabindex="-1">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <?= ui('toasts', ['initial' => $flash]) ?>
    <?= ui('modal', ['id' => 'confirm-dialog', 'title' => lang('Ui.confirmTitle'), 'body' => '<p data-confirm-text></p>', 'danger' => true]) ?>
    <?= $this->renderSection('scripts') ?>
</body>

</html>
