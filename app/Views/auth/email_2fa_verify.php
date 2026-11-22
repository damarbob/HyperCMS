<?php $this->setVar('title', lang('Ui.twoFactorTitle')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Ui.twoFactorTitle')) ?></h1>
    <p class="lede"><?= esc(lang('Ui.twoFactorLede', ['email' => (string) (auth()->user()?->email ?? '')])) ?></p>
</div>

<?= $this->include('auth/partials/messages') ?>

<form class="stack" action="<?= attr(url_to('auth-action-verify')) ?>" method="post">
    <?= csrf_field() ?>

    <?= ui('field', ['id' => 'token', 'name' => 'token', 'label' => lang('Ui.twoFactorCode'), 'kind' => 'text', 'required' => true, 'autocomplete' => 'one-time-code', 'placeholder' => '000000', 'attrs' => ['inputmode' => 'numeric', 'pattern' => '[0-9]*', 'autofocus' => true]]) ?>

    <?= ui('button', ['label' => lang('Ui.twoFactorButton'), 'type' => 'submit', 'variant' => 'primary']) ?>
</form>
<?= $this->endSection() ?>
