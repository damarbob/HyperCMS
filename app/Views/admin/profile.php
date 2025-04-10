<?php
helper('form');

$usernameError = validation_show_error('username');
$emailError = validation_show_error('email');
$passwordError = validation_show_error('password');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<h1 class="title">
    <?= auth()->user()->username ?>
</h1>
<p class="subtitle">
    <?= auth()->user()->email ?>
</p>
<form action="<?= base_url('admin/profile/' . urlencode(hash('sha256', auth()->user()->username . auth()->user()->email))) ?>" method="post">
    <?= csrf_field() ?>
    <div class="field mb-4">
        <label class="label" for="text_example">Username</label>
        <div class="control has-icons-right">
            <input type="username" id="username" name="username" class="input <?= ($usernameError) ? 'is-danger' : '' ?>" value="<?= old('username') ?: auth()->user()->username ?>">
            <?php if ($usernameError): ?>
                <span class="icon is-small is-right"><i class="fas fa-exclamation-triangle"></i></span>
            <?php endif; ?>
        </div>
        <?php if ($usernameError): ?>
            <p class="help is-danger"><?= $usernameError ?></p>
        <?php endif; ?>
    </div>
    <div class="field mb-4">
        <label class="label" for="text_example">Email</label>
        <div class="control has-icons-right">
            <input type="email" id="email" name="email" class="input <?= ($emailError) ? 'is-danger' : '' ?>" value="<?= old('email') ?: auth()->user()->email ?>">
            <?php if ($emailError): ?>
                <span class="icon is-small is-right"><i class="fas fa-exclamation-triangle"></i></span>
            <?php endif; ?>
        </div>
        <?php if ($emailError): ?>
            <p class="help is-danger"><?= $emailError ?></p>
        <?php endif; ?>
    </div>
    <div class="field mb-4">
        <label class="label" for="text_example">New password</label>
        <div class="control has-icons-right">
            <input type="password" id="password" name="password" class="input <?= ($passwordError) ? 'is-danger' : '' ?>" placeholder="New password">
            <?php if ($passwordError): ?>
                <span class="icon is-small is-right"><i class="fas fa-exclamation-triangle"></i></span>
            <?php endif; ?>
        </div>
        <?php if ($passwordError): ?>
            <p class="help is-danger"><?= $passwordError ?></p>
        <?php endif; ?>
    </div>
    <div class="field is-grouped">
        <div class="control is-flex-grow-1">
            <button type="submit" class="button is-primary">Save</button>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->endSection() ?>