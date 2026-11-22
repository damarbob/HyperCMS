<?php
/**
 * The page the error views share. It needs no session, no database and no helper of the admin, because it may be drawn
 * while something else is broken, so it reads the few things it needs from the request itself.
 *
 * The view that includes it sets `$heading` and `$body`, and may set `$code`.
 */

$root  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$path  = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$admin = preg_match('#(^|/)admin(/|$)#', $path) === 1;
$theme = in_array($_COOKIE['hyper_theme'] ?? '', ['light', 'dark'], true) ? $_COOKIE['hyper_theme'] : '';
$to    = $admin ? $root . '/admin/dashboard' : ($root === '' ? '/' : $root . '/');
$label = $admin ? lang('Ui.backToDashboard') : lang('Ui.goBack');
$code  = $code ?? 0;
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>"<?= $theme !== '' ? ' data-theme="' . $theme . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc($heading) ?></title>
    <link rel="stylesheet" href="<?= esc($root, 'attr') ?>/assets/ui/tokens.css">
    <link rel="stylesheet" href="<?= esc($root, 'attr') ?>/assets/ui/base.css">
    <link rel="stylesheet" href="<?= esc($root, 'attr') ?>/assets/ui/components.css">
    <style>body { margin: 0; font-family: system-ui, sans-serif; }</style>
</head>

<body>
    <main class="bare">
        <div class="bare-card">
            <div class="bare-sheet" style="align-items: flex-start">
                <?php if ($code > 0) : ?><p class="muted mono"><?= (int) $code ?></p><?php endif ?>
                <div>
                    <h1><?= esc($heading) ?></h1>
                    <p class="lede"><?= esc($body) ?></p>
                </div>
                <a class="btn primary" href="<?= esc($to, 'attr') ?>"><?= esc($label) ?></a>
            </div>
        </div>
    </main>
</body>

</html>
