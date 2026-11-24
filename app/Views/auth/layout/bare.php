<?php
/**
 * The frame of the pages shown before anyone is signed in, such as sign in and the magic link. It carries the colour
 * scheme from the cookie, since there is no signed-in user yet, and needs no scripts.
 *
 * A page extends it and writes its body in the `content` section, and may set `$title`, and `$wide` for a longer form.
 */

$hyperConfig = config(\Config\Hyper::class);
$theme       = admin_theme();
$version     = esc((string) $hyperConfig->appVersion, 'url');
$asset       = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);
?>
<!doctype html>
<html lang="<?= attr(service('request')->getLocale()) ?>"<?= $theme !== 'system' ? ' data-theme="' . attr($theme) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc(($title ?? '') !== '' ? $title . ' · ' . $hyperConfig->appName : $hyperConfig->appName) ?></title>
    <link rel="stylesheet" href="<?= $asset('ui/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/components.css') ?>">
</head>

<body>
    <main class="bare">
        <div class="bare-card<?= ! empty($wide) ? ' wide' : '' ?>">
            <a class="brand" href="<?= attr(base_url('admin/dashboard')) ?>">
                <span class="logo"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 1.5l2.2 5.1 5.5.5-4.2 3.7 1.3 5.4L10 13.4l-4.8 2.8 1.3-5.4L2.3 7.1l5.5-.5z" /></svg></span>
                <?= esc($hyperConfig->appName) ?>
            </a>

            <div class="bare-sheet">
                <?= $this->renderSection('content') ?>
            </div>

            <div class="bare-foot"><?= $this->renderSection('foot') ?></div>
        </div>
    </main>
</body>

</html>
