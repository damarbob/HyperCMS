<?php
/** @var App\Cells\FilterBarCell $this */
?>
<div class="stack" style="gap: var(--s3)">
    <form class="toolbar"<?= $this->formAttributes() ?>>
        <input class="input grow" type="search" name="q" value="<?= attr($q) ?>" placeholder="<?= attr($searchLabel) ?>" aria-label="<?= attr($searchLabel) ?>">
        <?= $fields ?>
        <noscript><button class="btn" type="submit"><?= esc(lang('Ui.search')) ?></button></noscript>
    </form>
    <?php if ($applied !== [] || $note !== '') : ?>
        <div class="applied">
            <?php foreach ($applied as $chip) : ?><?= ui('chip', $chip + ['tone' => 'tint']) ?><?php endforeach ?>
            <?php if ($note !== '') : ?><span style="margin-left:auto"><?= esc($note) ?></span><?php endif ?>
        </div>
    <?php endif ?>
</div>
