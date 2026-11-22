<?php
/** @var App\Cells\FieldCell $this */
?>
<div class="field<?= $error !== '' ? ' invalid' : '' ?>">
    <label for="<?= attr($id) ?>"><?= esc($label) ?><?= $required ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></label>
    <?= $this->control() ?>
    <?php if ($help !== '') : ?><span class="help" id="<?= attr($id) ?>-help"><?= esc($help) ?></span><?php endif ?>
    <?php if ($error !== '') : ?><div class="error" id="<?= attr($id) ?>-error"><?= icon('alert') ?><span><?= esc($error) ?></span></div><?php endif ?>
</div>
