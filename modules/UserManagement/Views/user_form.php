<?php
/**
 * One person: their name, email, password and roles, and the actions to ban or remove the account. A role the person who
 * is signed in may not hand out is shown locked, and stays as it is.
 *
 * @var \CodeIgniter\Shield\Entities\User|null $account
 * @var array<string, mixed>                   $values
 * @var array<string, string>                  $errors
 * @var list<array<string, mixed>>             $roles
 * @var bool                                   $isSelf
 * @var bool                                   $editable
 * @var bool                                   $banned
 * @var string                                 $banNote
 * @var bool                                   $last
 * @var string                                 $formUrl
 * @var string                                 $base
 */

$isNew = $account === null;
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc($isNew ? lang('UserManagement.newUser') : (string) $account->username) ?></h1>
        <?php if (! $isNew) : ?>
            <p>
                <?php if ($banned) : ?><span class="chip bad"><?= esc(lang('UserManagement.statusBanned')) ?></span><?php endif ?>
                <?php if ($isSelf) : ?><span class="chip tint"><?= esc(lang('UserManagement.thisIsYou')) ?></span><?php endif ?>
            </p>
        <?php endif ?>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('UserManagement.back'), 'href' => base_url('admin/users'), 'icon' => 'chevron-left']) ?>
    </div>
</div>

<?php if (isset($errors['general'])) : ?>
    <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($errors['general']) ?></span></div>
<?php endif ?>
<?php if (! $editable) : ?>
    <div class="alert warn" role="status"><?= icon('info') ?><span><?= esc(lang('UserManagement.notAllowedToHandle')) ?></span></div>
<?php endif ?>

<form class="stack" style="gap: var(--s5)" method="post" action="<?= attr($formUrl) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <section class="panel" aria-labelledby="user-details">
        <header><h2 id="user-details"><?= esc(lang('UserManagement.accountTitle')) ?></h2></header>
        <div class="body details-grid">
            <div class="field <?= isset($errors['username']) ? 'invalid' : '' ?>">
                <label for="u-username"><?= esc(lang('UserManagement.username')) ?><span class="req" aria-hidden="true">*</span></label>
                <input id="u-username" class="input" type="text" name="username" value="<?= attr((string) $values['username']) ?>" required autocomplete="off" <?= $editable ? '' : 'readonly' ?>>
                <?php if (isset($errors['username'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['username']) ?></p><?php endif ?>
            </div>
            <div class="field <?= isset($errors['email']) ? 'invalid' : '' ?>">
                <label for="u-email"><?= esc(lang('UserManagement.email')) ?><span class="req" aria-hidden="true">*</span></label>
                <input id="u-email" class="input" type="email" name="email" value="<?= attr((string) $values['email']) ?>" required autocomplete="off" <?= $editable ? '' : 'readonly' ?>>
                <?php if (isset($errors['email'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['email']) ?></p><?php endif ?>
            </div>
            <div class="field wide <?= isset($errors['password']) ? 'invalid' : '' ?>">
                <label for="u-password"><?= esc($isNew ? lang('UserManagement.password') : lang('UserManagement.newPassword')) ?><?= $isNew ? '<span class="req" aria-hidden="true">*</span>' : '' ?></label>
                <input id="u-password" class="input" type="password" name="password" autocomplete="new-password" <?= $isNew ? 'required' : '' ?> <?= $editable ? '' : 'readonly' ?> aria-describedby="u-password-help">
                <p class="help" id="u-password-help"><?= esc($isNew ? lang('UserManagement.passwordHelp') : lang('UserManagement.leaveBlankToKeepCurrentPassword')) ?></p>
                <?php if (isset($errors['password'])) : ?><p class="error"><?= icon('alert') ?><?= esc($errors['password']) ?></p><?php endif ?>
            </div>
        </div>
    </section>

    <section class="panel" aria-labelledby="user-roles">
        <header>
            <div>
                <h2 id="user-roles"><?= esc(lang('UserManagement.rolesTitle')) ?></h2>
                <p class="help" style="margin: 2px 0 0"><?= esc(lang('UserManagement.rolesHelp')) ?></p>
            </div>
        </header>
        <div class="body options" style="flex-direction: column; gap: var(--s3)">
            <?php foreach ($roles as $role) : ?>
                <label class="ability"<?= $role['allowed'] && $editable ? '' : ' title="' . attr(lang('UserManagement.roleLocked')) . '"' ?>>
                    <input type="checkbox" name="roles[]" value="<?= attr($role['name']) ?>"<?= $role['has'] ? ' checked' : '' ?><?= $role['allowed'] && $editable ? '' : ' disabled' ?>>
                    <span>
                        <strong><?= esc($role['label']) ?></strong>
                        <?php if ($role['description'] !== '') : ?><span class="muted"><?= esc($role['description']) ?></span><?php endif ?>
                    </span>
                </label>
            <?php endforeach ?>
        </div>
    </section>

    <?php if ($editable) : ?>
        <div><?= ui('button', ['label' => $isNew ? lang('UserManagement.create') : lang('UserManagement.save'), 'type' => 'submit', 'variant' => 'primary']) ?></div>
    <?php endif ?>
</form>

<?php if (! $isNew && $editable && ! $isSelf) : ?>
    <section class="panel" aria-labelledby="user-danger" style="margin-top: var(--s5)">
        <header><h2 id="user-danger"><?= esc(lang('UserManagement.dangerTitle')) ?></h2></header>
        <div class="body stack" style="gap: var(--s4)">
            <?php if ($last) : ?>
                <div class="alert warn" role="status"><?= icon('info') ?><span><?= esc(lang('UserManagement.lastSuperadmin')) ?></span></div>
            <?php else : ?>
                <?php if ($banned) : ?>
                    <form method="post" action="<?= attr($base . '/unban') ?>" class="stack" style="gap: var(--s2)">
                        <?= csrf_field() ?>
                        <p><?= esc(lang('UserManagement.bannedText')) ?><?= $banNote !== '' ? ' ' . esc($banNote) : '' ?></p>
                        <div><?= ui('button', ['label' => lang('UserManagement.unban'), 'type' => 'submit', 'icon' => 'refresh']) ?></div>
                    </form>
                <?php else : ?>
                    <form method="post" action="<?= attr($base . '/ban') ?>" class="stack" style="gap: var(--s2)">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="u-ban"><?= esc(lang('UserManagement.banReason')) ?></label>
                            <input id="u-ban" class="input" type="text" name="message" maxlength="255" autocomplete="off" aria-describedby="u-ban-help">
                            <p class="help" id="u-ban-help"><?= esc(lang('UserManagement.banHelp')) ?></p>
                        </div>
                        <div><?= ui('button', ['label' => lang('UserManagement.ban'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'lock']) ?></div>
                    </form>
                <?php endif ?>
                <form method="post" action="<?= attr($base . '/delete') ?>" onsubmit="return confirm(<?= attr(json_encode(lang('UserManagement.confirmDelete'), JSON_UNESCAPED_UNICODE)) ?>)">
                    <?= csrf_field() ?>
                    <?= ui('button', ['label' => lang('UserManagement.delete'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
                </form>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
