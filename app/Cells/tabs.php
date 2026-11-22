<?php
/** @var App\Cells\TabsCell $this */
?>
<nav class="tabs"<?= $label !== '' ? ' aria-label="' . attr($label) . '"' : '' ?>>
    <?php foreach ($items as $item) : ?>
        <a href="<?= attr($item['href']) ?>"<?= ! empty($item['current']) ? ' aria-current="page"' : '' ?>><?= esc($item['label']) ?></a>
    <?php endforeach ?>
</nav>
