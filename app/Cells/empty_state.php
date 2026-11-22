<?php
/** @var App\Cells\EmptyStateCell $this */
?>
<div class="empty">
    <span class="glyph"><?= icon($icon) ?></span>
    <h2><?= esc($title) ?></h2>
    <?php if ($text !== '') : ?><p><?= esc($text) ?></p><?php endif ?>
    <?php if ($actionLabel !== '' && $actionHref !== '') : ?>
        <?= ui('button', ['label' => $actionLabel, 'href' => $actionHref, 'variant' => 'primary', 'icon' => 'plus']) ?>
    <?php endif ?>
</div>
