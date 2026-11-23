<?php
/**
 * The library as a page of its own, for a screen that shows it inside a frame, such as the page editor. A file the person
 * chooses goes to the page that opened the frame as a message, `filesSelected_r{requester}`, with the list of the links of
 * the files, which is what that screen already listens for.
 *
 * @var string $requester The code the opening page gave, in hexadecimal.
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
    <meta name="csrf-header" content="<?= attr(csrf_header()) ?>">
    <meta name="csrf-token" content="<?= attr(csrf_hash()) ?>">
    <meta name="assets-base" content="<?= attr(base_url('assets/')) ?>">
    <meta name="site-base" content="<?= attr(base_url()) ?>">
    <title><?= esc(lang('Media.moduleName')) ?></title>
    <link rel="stylesheet" href="<?= $asset('ui/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('ui/components.css') ?>">
    <script src="<?= $asset('ui/ui.js') ?>" defer></script>
    <script src="<?= $asset('ui/media.js') ?>" defer></script>
    <script src="<?= $asset('vendor/htmx.org/dist/htmx.min.js') ?>" defer></script>
    <script src="<?= $asset('vendor/alpinejs/dist/cdn.min.js') ?>" defer></script>
</head>

<body class="media-frame" data-requester="<?= attr($requester) ?>">
    <main>
        <?= view('Media\Views\partials\browser', get_defined_vars()) ?>
    </main>
    <?= ui('toasts', ['initial' => []]) ?>
    <?= ui('modal', ['id' => 'confirm-dialog', 'title' => lang('Ui.confirmTitle'), 'body' => '<p data-confirm-text></p>', 'danger' => true]) ?>
    <script>
        (function () {
            var requester = document.body.getAttribute('data-requester');
            function send(links) {
                if (window.parent === window) return;
                window.parent.postMessage({ action: 'filesSelected_r' + requester, data: links }, window.location.origin);
            }
            document.addEventListener('media-choose', function (event) {
                send((event.detail.files || []).map(function (file) { return file.url; }));
            });
            document.addEventListener('media-cancel', function () { send([]); });
        })();
    </script>
</body>
</html>
