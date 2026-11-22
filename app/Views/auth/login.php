<?php $this->setVar('title', lang('Ui.signInTitle')); ?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Ui.signInTitle')) ?></h1>
    <p class="lede"><?= esc(lang('Ui.signInLede')) ?></p>
</div>

<?= $this->include('auth/partials/messages') ?>

<form class="stack" action="<?= attr(url_to('login')) ?>" method="post">
    <?= csrf_field() ?>

    <?= ui('field', ['id' => 'email', 'label' => lang('Ui.email'), 'kind' => 'email', 'value' => (string) old('email'), 'required' => true, 'autocomplete' => 'username', 'attrs' => ['autofocus' => true, 'inputmode' => 'email']]) ?>
    <?= ui('field', ['id' => 'password', 'label' => lang('Ui.password'), 'kind' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>

    <?php if (setting('Auth.sessionConfig')['allowRemembering']) : ?>
        <label class="check"><input type="checkbox" name="remember" <?= old('remember') ? 'checked' : '' ?>><?= esc(lang('Ui.rememberMe')) ?></label>
    <?php endif ?>

    <?= ui('button', ['label' => lang('Ui.signInButton'), 'type' => 'submit', 'variant' => 'primary']) ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<?php if (setting('Auth.allowMagicLinkLogins')) : ?><a href="<?= attr(url_to('magic-link')) ?>"><?= esc(lang('Ui.forgotPassword')) ?></a><?php endif ?>
<?php if (setting('Auth.allowRegistration')) : ?><a href="<?= attr(url_to('register')) ?>"><?= esc(lang('Ui.noAccount')) ?></a><?php endif ?>
<?= $this->endSection() ?>
