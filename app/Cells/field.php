<?php
/** @var App\Cells\FieldCell $this */
$caption = esc($label) . ($required ? ' <span class="req" aria-hidden="true">*</span>' : '');
$notes   = ($help !== '' ? '<span class="help" id="' . attr($id) . '-help">' . esc($help) . '</span>' : '')
    . ($error !== '' ? '<div class="error" id="' . attr($id) . '-error">' . icon('alert') . '<span>' . esc($error) . '</span></div>' : '');
?>
<?php if ($group) : ?>
<fieldset class="field<?= $error !== '' ? ' invalid' : '' ?>" id="<?= attr($id) ?>">
    <legend><?= $caption ?></legend>
    <?= $this->control() ?>
    <?= $notes ?>
</fieldset>
<?php else : ?>
<div class="field<?= $error !== '' ? ' invalid' : '' ?>">
    <label for="<?= attr($id) ?>"><?= $caption ?></label>
    <?= $this->control() ?>
    <?= $notes ?>
</div>
<?php endif ?>
