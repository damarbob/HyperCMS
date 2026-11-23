<?php
/**
 * The page editor: a thin top bar of the admin and GrapesJS filling the rest of the window.
 *
 * GrapesJS draws its own panels, and only its documented colour variables are set, in `ui/editor.css`, so the editor follows
 * the colour scheme of the admin and nothing in it is restyled by hand. The bar above it is made of the admin's own parts.
 * The script `ui/editor.js` reads what it needs from the JSON block below.
 *
 * @var array<string, mixed> $editor The data for the script.
 * @var string               $title
 */

$hyperConfig = config(\Config\Hyper::class);
$theme       = admin_theme();
$version     = esc((string) $hyperConfig->appVersion, 'url');
$asset       = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);
$module      = static fn (string $path): string => attr(module_assets_url('PagingSystem', $path));
$labels      = $editor['labels'];
?>
<!doctype html>
<html lang="<?= attr(service('request')->getLocale()) ?>"<?= $theme !== 'system' ? ' data-theme="' . attr($theme) . '"' : '' ?>
    data-error-text="<?= attr(lang('Ui.requestFailed')) ?>" data-offline-text="<?= attr(lang('Ui.offline')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-header" content="<?= attr(csrf_header()) ?>">
    <meta name="csrf-token" content="<?= attr(csrf_hash()) ?>">
    <meta name="site-base" content="<?= attr(base_url()) ?>">
    <title><?= esc($title) ?></title>

    <link rel="stylesheet" href="<?= $asset('ui/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/components.css') ?>">

    <?php // GrapesJS, its icon font and its plugins are third party files, kept at the versions they were tried with. ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/css/grapes.min.css" integrity="sha256-1I3el0pvNWfTUd1SEXRESKNhHSpnELFugVLiTDt//cY=" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://unpkg.com/grapick@0.1.13/dist/grapick.min.css" integrity="sha384-4EXeVJ0v8SVgj6nciaiDBqG7tZ6oz+cWouBaHyZLBAhd6Gaf9qSv1Tew1I41bM/T" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= $asset('ui/editor.css') ?>">

    <script src="<?= $asset('ui/ui.js') ?>" defer></script>
    <script src="<?= $asset('ui/media.js') ?>" defer></script>
    <script src="<?= $asset('vendor/htmx.org/dist/htmx.min.js') ?>" defer></script>
    <script src="<?= $asset('vendor/alpinejs/dist/cdn.min.js') ?>" defer></script>
</head>

<body class="editor-app">
    <header class="editor-bar">
        <a class="btn quiet" href="<?= attr($editor['back']) ?>"><?= icon('chevron-left') ?><span><?= esc($labels['back']) ?></span></a>
        <h1 class="editor-title"><?= esc($editor['pageTitle']) ?></h1>

        <div class="seg" role="group" aria-label="<?= attr($labels['devices']) ?>" id="editor-devices">
            <?php foreach (['desktop', 'tablet', 'mobile'] as $device) : ?>
                <button type="button" data-device="<?= attr($device) ?>" aria-pressed="<?= $device === 'desktop' ? 'true' : 'false' ?>"><?= esc($labels[$device]) ?></button>
            <?php endforeach ?>
        </div>

        <div class="editor-actions">
            <button class="btn icon-only quiet" type="button" id="editor-undo" aria-label="<?= attr($labels['undo']) ?>" title="<?= attr($labels['undo']) ?>" disabled><?= icon('undo') ?></button>
            <button class="btn icon-only quiet" type="button" id="editor-redo" aria-label="<?= attr($labels['redo']) ?>" title="<?= attr($labels['redo']) ?>" disabled><?= icon('redo') ?></button>
            <span class="editor-state muted" id="editor-state" role="status"></span>
            <button class="btn primary" type="button" id="editor-save"><?= icon('check') ?><span><?= esc($labels['save']) ?></span><span class="spin" aria-hidden="true"></span></button>
        </div>
    </header>

    <div id="gjs"></div>

    <?= ui('toasts', ['initial' => []]) ?>

    <script type="application/json" id="editor-data"><?= json_encode($editor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

    <script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/grapes.min.js" integrity="sha384-VVdz9itcBtjOngcFcoIquT0kc0IryHoqxrEbyWuLyYxes4gk28appuaGr12hXe4K" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-parser-postcss@1.0.3/dist/index.min.js" integrity="sha384-4MvfYqEaALJ+EDGIH3J/jz58vJRRbvBfufO1GQ4IZ/3iybe5LWwYtkFAU/7AaBkb" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.min.js" integrity="sha384-y0dSmesJ5kUnMWIjw5wYX/y09jBY4VuR+tw/NuYOFc1CamxStdOgoHPiUzfLRBi4" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-tui-image-editor@1.0.2/dist/index.min.js" integrity="sha384-1bmlrsmTvqNZ8YSUOontb9u0fisgNUMyCUshoHapUT9MI3VbK1Piv6oJcY2kN/Xh" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-style-gradient@3.0.3/dist/index.min.js" integrity="sha384-KZ1FVEQCSWCeYSqM3Vs/sEpkjS5aQgLR7ymvgdYJdGa0w09XC3WP7sBWibmbaiGM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-style-bg@2.0.2/dist/index.min.js" integrity="sha384-KWFNfHKc2cmy1JvItSQabjR+KpkCMriKYH6erHub3eLVJhEIETBPmiBRiXp9U/WH" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/grapesjs-style-filter@1.0.2/dist/index.min.js" integrity="sha384-cIRi7cfk0/QMC7mY98KBjOYqyX02ZcSoIlu0ndO20QcIZsymBbfzWJZ7yorIMxIF" crossorigin="anonymous"></script>

    <?php // The Bootstrap 5 blocks. Each file adds one plugin, and modules run in the order of the page, before the editor script. ?>
    <?php foreach (['grapesjs-blocks-bootstrap-5.min', 'grapesjs-bootstrap-grid-system', 'grapesjs-bootstrap-card', 'grapesjs-bootstrap-button', 'grapesjs-bootstrap-typography', 'grapesjs-bootstrap-accordion', 'grapesjs-bootstrap-carousel', 'grapesjs-bootstrap-forms', 'grapesjs-bootstrap-alert', 'grapesjs-bootstrap-badge', 'grapesjs-bootstrap-breadcrumb', 'grapesjs-bootstrap-dropdown', 'grapesjs-bootstrap-navbar'] as $block) : ?>
        <script type="module" src="<?= $module('blocks/bootstrap5/' . $block . '.js') ?>"></script>
    <?php endforeach ?>
    <script type="module" src="<?= $asset('ui/editor.js') ?>"></script>
</body>
</html>
