<?php $this->setVar('title', lang('Ui.magicLinkSent')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Ui.magicLinkSent')) ?></h1>
    <p class="lede"><?= esc(lang('Ui.magicLinkSentText')) ?></p>
</div>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<a href="<?= attr(url_to('login')) ?>"><?= esc(lang('Ui.backToSignIn')) ?></a>
<?= $this->endSection() ?>
