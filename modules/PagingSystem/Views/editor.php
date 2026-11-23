<?php
/**
 * The page editor, as a page of its own that fills the window. GrapesJS and the panels that the editor script builds are an
 * island inside it, and everything around the island is the admin: the colours follow the colour scheme of the person, the
 * answers of a save come as toasts, and the media library opens in a dialog.
 *
 * @var array<string, mixed> $entry
 * @var string               $title
 */

$hyperConfig = config(\Config\Hyper::class);
$theme       = admin_theme();
$version     = esc((string) $hyperConfig->appVersion, 'url');
$asset       = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);
?>
<!doctype html>
<html lang="<?= attr(service('request')->getLocale()) ?>"<?= $theme !== 'system' ? ' data-theme="' . attr($theme) . '"' : '' ?>
    data-error-text="<?= attr(lang('Ui.requestFailed')) ?>" data-offline-text="<?= attr(lang('Ui.offline')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc($title) ?></title>
    <link rel="stylesheet" href="<?= $asset('ui/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/components.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/editor.css') ?>">

    <?php // The editor itself and its icons are scripts and styles of their own, kept at the versions they were made for. ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/css/grapes.min.css" integrity="sha256-1I3el0pvNWfTUd1SEXRESKNhHSpnELFugVLiTDt//cY=" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unpkg.com/grapick@0.1.13/dist/grapick.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <script src="<?= $asset('ui/ui.js') ?>" defer></script>
    <script src="<?= $asset('vendor/alpinejs/dist/cdn.min.js') ?>" defer></script>
</head>

<body class="editor-app">
    <div id="gjs-editor-container"></div>

    <?php // The media library, in a frame. It tells this page which images were chosen. ?>
    <div id="fileManagerModal" class="modal" role="dialog" aria-modal="true" aria-label="<?= attr(lang('Media.moduleName')) ?>">
        <div class="modal-background"></div>
        <div class="modal-card is-fullheight">
            <div class="modal-card-body is-flex">
                <iframe class="is-flex-grow-1" id="fileManagerIframe" title="<?= attr(lang('Media.moduleName')) ?>"></iframe>
            </div>
        </div>
        <button class="modal-close" type="button" aria-label="<?= attr(lang('Ui.close')) ?>"></button>
    </div>

    <?= ui('toasts', ['initial' => []]) ?>

    <script src="<?= attr(base_url('assets/vendor/jquery/dist/jquery.min.js')) ?>"></script>
    <script src="<?= $asset('ui/editor-support.js') ?>"></script>
    <?= $this->include('PagingSystem\Views\editor_scripts') ?>
</body>
</html>
