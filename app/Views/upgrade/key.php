<?php
/**
 * The first page of the upgrade, which asks for the key in the writable folder.
 *
 * @var string $keyPath
 * @var string $error
 */
$this->setVar('title', lang('Upgrade.title'));
?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Upgrade.title')) ?></h1>
    <p class="lede"><?= esc(lang('Upgrade.lede')) ?></p>
</div>

<form class="stack" action="<?= attr(site_url('upgrade')) ?>" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <h2><?= esc(lang('Upgrade.keyTitle')) ?></h2>
    <?= ui('field', ['id' => 'key', 'label' => lang('Upgrade.keyLabel'), 'required' => true, 'error' => $error, 'help' => lang('Upgrade.keyHelp', ['path' => $keyPath]), 'autocomplete' => 'off']) ?>
    <?= ui('button', ['label' => lang('Upgrade.keyButton'), 'type' => 'submit', 'variant' => 'primary']) ?>
</form>
<?= $this->endSection() ?>
