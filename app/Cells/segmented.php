<?php
/** @var App\Cells\SegmentedCell $this */
?>
<div class="seg" role="group"<?= $label !== '' ? ' aria-label="' . attr($label) . '"' : '' ?>>
    <?php foreach ($items as $item) : ?>
        <a href="<?= attr($item['href']) ?>" aria-current="<?= ! empty($item['current']) ? 'true' : 'false' ?>"<?= attrs($item['attrs'] ?? []) ?>><?= esc($item['label']) ?><?php if (isset($item['count'])) : ?><span class="n"><?= esc((string) $item['count']) ?></span><?php endif ?></a>
    <?php endforeach ?>
</div>
