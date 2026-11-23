<?php
/**
 * The account of the signed-in person.
 *
 * @var \CodeIgniter\Shield\Entities\User $account
 * @var array{username: string, email: string} $values
 * @var array<string, string>             $errors
 * @var list<string>                      $roles
 * @var bool                              $twoFactor
 * @var bool                              $tokens
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc((string) $account->username) ?></h1>
        <p><?= esc((string) $account->email) ?></p>
    </div>
</div>

<?php if (isset($errors['general'])) : ?>
    <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($errors['general']) ?></span></div>
<?php endif ?>

<form class="stack" style="gap: var(--s5)" method="post" action="<?= attr(base_url('admin/profile')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel" aria-labelledby="p-account">
        <header><h2 id="p-account"><?= esc(lang('Profile.accountTitle')) ?></h2></header>
        <div class="body details-grid">
            <div class="field <?= isset($errors['username']) ? 'invalid' : '' ?>">
                <label for="p-username"><?= esc(lang('Profile.username')) ?></label>
                <input id="p-username" class="input" type="text" name="username" value="<?= attr($values['username']) ?>" required autocomplete="username">
                <?php if (isset($errors['username'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['username']) ?></p><?php endif ?>
            </div>
            <div class="field <?= isset($errors['email']) ? 'invalid' : '' ?>">
                <label for="p-email"><?= esc(lang('Profile.email')) ?></label>
                <input id="p-email" class="input" type="email" name="email" value="<?= attr($values['email']) ?>" required autocomplete="email">
                <?php if (isset($errors['email'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['email']) ?></p><?php endif ?>
            </div>
            <div class="field wide">
                <span class="label" style="font-weight: 550; font-size: var(--t-sm)"><?= esc(lang('Profile.roles')) ?></span>
                <div class="chips"><?php foreach ($roles as $role) : ?><span class="chip"><?= esc($role) ?></span><?php endforeach ?><?php if ($roles === []) : ?><span class="muted"><?= esc(lang('Profile.noRoles')) ?></span><?php endif ?></div>
            </div>
        </div>
    </section>

    <section class="panel" aria-labelledby="p-password">
        <header>
            <div>
                <h2 id="p-password"><?= esc(lang('Profile.passwordTitle')) ?></h2>
                <p class="help" style="margin: 2px 0 0"><?= esc(lang('Profile.passwordHelp')) ?></p>
            </div>
        </header>
        <div class="body details-grid">
            <div class="field <?= isset($errors['password']) ? 'invalid' : '' ?>">
                <label for="p-password-new"><?= esc(lang('Profile.newPassword')) ?></label>
                <input id="p-password-new" class="input" type="password" name="password" autocomplete="new-password">
                <?php if (isset($errors['password'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['password']) ?></p><?php endif ?>
            </div>
            <div class="field <?= isset($errors['current_password']) ? 'invalid' : '' ?>">
                <label for="p-password-current"><?= esc(lang('Profile.currentPassword')) ?></label>
                <input id="p-password-current" class="input" type="password" name="current_password" autocomplete="current-password" aria-describedby="p-current-help">
                <p class="help" id="p-current-help"><?= esc(lang('Profile.currentPasswordHelp')) ?></p>
                <?php if (isset($errors['current_password'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['current_password']) ?></p><?php endif ?>
            </div>
        </div>
    </section>

    <div><?= ui('button', ['label' => lang('Profile.save'), 'type' => 'submit', 'variant' => 'primary']) ?></div>
</form>

<section class="panel" aria-labelledby="p-security" style="margin-top: var(--s5)">
    <header><h2 id="p-security"><?= esc(lang('Profile.securityTitle')) ?></h2></header>
    <div class="body stack" style="gap: var(--s3)">
        <p>
            <span class="chip <?= $twoFactor ? 'ok' : '' ?>"><?= esc($twoFactor ? lang('Profile.twoFactorOn') : lang('Profile.twoFactorOff')) ?></span>
            <?= esc($twoFactor ? lang('Profile.twoFactorOnText') : lang('Profile.twoFactorOffText')) ?>
        </p>
        <?php if ($tokens) : ?>
            <div><?= ui('button', ['label' => lang('Profile.openTokens'), 'href' => base_url('admin/tokens'), 'icon' => 'key']) ?></div>
        <?php endif ?>
    </div>
</section>
<?= $this->endSection() ?>
