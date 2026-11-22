<?php $this->setVar('title', lang('Ui.registerTitle')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Ui.registerTitle')) ?></h1>
    <p class="lede"><?= esc(lang('Ui.registerLede')) ?></p>
</div>

<?= $this->include('auth/partials/messages') ?>

<form class="stack" action="<?= attr(url_to('register')) ?>" method="post">
    <?= csrf_field() ?>

    <?= ui('field', ['id' => 'email', 'label' => lang('Ui.email'), 'kind' => 'email', 'value' => (string) old('email'), 'required' => true, 'autocomplete' => 'email', 'attrs' => ['inputmode' => 'email']]) ?>
    <?= ui('field', ['id' => 'username', 'label' => lang('Ui.username'), 'value' => (string) old('username'), 'required' => true, 'autocomplete' => 'username']) ?>
    <?= ui('field', ['id' => 'password', 'label' => lang('Ui.password'), 'kind' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
    <?= ui('field', ['id' => 'password_confirm', 'label' => lang('Ui.passwordAgain'), 'kind' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>

    <?= ui('button', ['label' => lang('Ui.registerButton'), 'type' => 'submit', 'variant' => 'primary']) ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<?= esc(lang('Ui.haveAccount')) ?> <a href="<?= attr(url_to('login')) ?>"><?= esc(lang('Ui.signInTitle')) ?></a>
<?= $this->endSection() ?>
