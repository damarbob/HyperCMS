<?php
/**
 * The messages the last request left for an auth page: one error, a list of errors, or a plain message. They are escaped,
 * because they can hold what the person typed.
 */
$errors = [];
if (session('error') !== null) {
    $errors = (array) session('error');
} elseif (session('errors') !== null) {
    $errors = (array) session('errors');
}
$message = session('message');
?>
<?php if ($errors !== []) : ?>
    <div class="alert" role="alert">
        <?= icon('alert') ?>
        <ul>
            <?php foreach ($errors as $error) : ?><li><?= esc((string) $error) ?></li><?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
<?php if ($message !== null) : ?>
    <div class="alert ok" role="status"><?= icon('check') ?><span><?= esc((string) $message) ?></span></div>
<?php endif ?>
