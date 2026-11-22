<?php
/** @var App\Cells\ModalCell $this */
?>
<dialog class="modal" id="<?= attr($id) ?>" aria-labelledby="<?= attr($id) ?>-title">
    <form method="dialog">
        <div class="head"><h2 id="<?= attr($id) ?>-title"><?= esc($title) ?></h2></div>
        <div class="content"><?= $body ?></div>
        <div class="foot">
            <button class="btn" value="cancel" data-confirm-cancel><?= esc($cancelLabel !== '' ? $cancelLabel : lang('Ui.cancel')) ?></button>
            <button class="btn <?= $danger ? 'danger solid' : 'primary' ?>" value="ok" data-confirm-ok><?= esc($confirmLabel !== '' ? $confirmLabel : lang('Ui.confirm')) ?></button>
        </div>
    </form>
</dialog>
