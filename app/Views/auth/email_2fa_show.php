<?php $this->setVar('title', lang('Ui.twoFactorSendTitle')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Ui.twoFactorSendTitle')) ?></h1>
    <p class="lede"><?= esc(lang('Ui.twoFactorSendLede')) ?></p>
</div>

<?= $this->include('auth/partials/messages') ?>

<form class="stack" action="<?= attr(url_to('auth-action-handle')) ?>" method="post">
    <?= csrf_field() ?>

    <?php /** @var CodeIgniter\Shield\Entities\User $user */ ?>
    <?= ui('field', ['id' => 'email', 'label' => lang('Ui.email'), 'kind' => 'email', 'value' => (string) old('email', $user->email), 'required' => true, 'autocomplete' => 'email', 'attrs' => ['inputmode' => 'email']]) ?>

    <?= ui('button', ['label' => lang('Ui.twoFactorSend'), 'type' => 'submit', 'variant' => 'primary', 'icon' => 'mail']) ?>
</form>
<?= $this->endSection() ?>
