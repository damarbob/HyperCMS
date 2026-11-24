<?php $this->setVar('title', lang('Install.readyTitle')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Install.readyTitle')) ?></h1>
    <p class="lede"><?= esc(lang('Install.readyLede')) ?></p>
</div>

<form class="stack" action="<?= attr(site_url('install/run')) ?>" method="post">
    <?= csrf_field() ?>
    <?= ui('button', ['label' => lang('Install.readyButton'), 'type' => 'submit', 'variant' => 'primary']) ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<a href="<?= attr(site_url('install')) ?>"><?= esc(lang('Install.startOver')) ?></a>
<?= $this->endSection() ?>
