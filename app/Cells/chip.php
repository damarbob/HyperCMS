<?php
/** @var App\Cells\ChipCell $this */
?>
<span class="<?= $this->classes() ?>"><?= esc($label) ?><?php if ($removeHref !== '') : ?>
    <a class="x" href="<?= attr($removeHref) ?>" aria-label="<?= attr($removeLabel !== '' ? $removeLabel : $label) ?>"<?= attrs($removeAttrs) ?>>&times;</a><?php endif ?></span>
