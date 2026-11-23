<?php
/**
 * The page a site shows at its home address while no module has taken that address. It says what is installed and where to
 * go next, and it is made of the same parts as the admin so the first thing a person sees belongs to the same product.
 */

use Config\Hyper;

helper('hyper_ui');

$config  = config(Hyper::class);
$theme   = admin_theme();
$version = esc((string) $config->appVersion, 'url');
$asset   = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);
$signed  = auth()->loggedIn();
?>
<!doctype html>
<html lang="<?= attr(service('request')->getLocale()) ?>"<?= $theme !== 'system' ? ' data-theme="' . attr($theme) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($config->appName) ?></title>
    <link rel="stylesheet" href="<?= $asset('ui/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/components.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/welcome.css') ?>">
</head>
<body class="welcome">
    <main>
        <p class="version"><?= esc((string) $config->appVersion) ?></p>
        <h1><?= esc($config->appName) ?></h1>
        <p class="text"><?= esc(lang('Dashboard.welcomeText')) ?></p>
        <p class="go">
            <?= ui('button', [
                'label'   => $signed ? lang('Dashboard.openAdmin') : lang('Dashboard.signIn'),
                'href'    => base_url($signed ? 'admin/dashboard' : 'auth/login'),
                'variant' => 'primary',
            ]) ?>
        </p>
    </main>
</body>
</html>
