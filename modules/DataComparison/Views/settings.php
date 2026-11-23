<?php
/**
 * The data sources of the person, as JSON.
 *
 * @var string $dataSources The JSON in the box.
 * @var string $error       What is wrong with the JSON that was sent, or an empty text.
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Dc.moduleName')) ?></h1>
        <p><?= esc(lang('Dc.dataSourcesText')) ?></p>
    </div>
</div>

<form class="stack" style="gap: var(--s4); max-width: 860px" action="<?= attr(base_url('admin/settings/data-comparison')) ?>" method="post">
    <?= csrf_field() ?>
    <?php if ($error !== '') : ?>
        <div class="alert bad" role="alert"><?= icon('alert') ?><span><?= esc($error) ?></span></div>
    <?php endif ?>
    <div class="field">
        <label for="dc-sources"><?= esc(lang('Dc.dataSources')) ?></label>
        <textarea id="dc-sources" class="textarea mono" name="dataSources" rows="18" spellcheck="false" data-island="code"><?= esc($dataSources) ?></textarea>
        <p class="help"><?= esc(lang('Dc.enterDataSourcesInJsonFormat')) ?></p>
    </div>
    <div><?= ui('button', ['label' => lang('Admin.save'), 'type' => 'submit', 'variant' => 'primary']) ?></div>
</form>
<?= $this->endSection() ?>
