<?php $this->setVar('title', lang('Ui.magicLinkTitle')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Ui.magicLinkTitle')) ?></h1>
    <p class="lede"><?= esc(lang('Ui.magicLinkLede')) ?></p>
</div>

<?= $this->include('auth/partials/messages') ?>

<form class="stack" action="<?= attr(url_to('magic-link')) ?>" method="post">
    <?= csrf_field() ?>

    <?= ui('field', ['id' => 'email', 'label' => lang('Ui.email'), 'kind' => 'email', 'value' => (string) old('email'), 'required' => true, 'autocomplete' => 'email', 'attrs' => ['autofocus' => true, 'inputmode' => 'email']]) ?>

    <?= ui('button', ['label' => lang('Ui.magicLinkSend'), 'type' => 'submit', 'variant' => 'primary', 'icon' => 'mail']) ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<a href="<?= attr(url_to('login')) ?>"><?= esc(lang('Ui.backToSignIn')) ?></a>
<?= $this->endSection() ?>
