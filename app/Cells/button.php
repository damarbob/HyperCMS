<?php
/** @var App\Cells\ButtonCell $this */
$tag = $href === '' ? 'button' : 'a';
?>
<<?= $tag ?> class="<?= $this->classes() ?>"<?= $this->attributes() ?>>
    <?= $icon !== '' ? icon($icon) : '' ?>
    <?php if (! $iconOnly) : ?><span><?= esc($label) ?></span><?php endif ?>
    <?php if ($tag === 'button') : ?><span class="spin" aria-hidden="true"></span><?php endif ?>
</<?= $tag ?>>
