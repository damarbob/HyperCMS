<?php
/**
 * The settings of the signed-in person and, for those who manage settings, of the site.
 *
 * @var int       $perPage
 * @var list<int> $perPages
 * @var bool      $manages
 * @var bool      $twoFactor
 * @var string    $error
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<?= service('hooks')->trigger(hook('Backend.view:settings')) ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Admin.settings')) ?></h1>
        <p><?= esc(lang('Admin.general')) ?></p>
    </div>
</div>

<?php if ($error !== '') : ?><div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($error) ?></span></div><?php endif ?>

<form class="stack" style="gap: var(--s5)" action="<?= attr(base_url('admin/settings/update')) ?>" method="post">
    <?= csrf_field() ?>
    <section class="panel" aria-labelledby="s-mine">
        <header><h2 id="s-mine"><?= esc(lang('Profile.settingsMine')) ?></h2></header>
        <div class="body">
            <div class="field" style="max-width: 320px">
                <label for="s-per-page"><?= esc(lang('Admin.datatableEntriesPerPage')) ?></label>
                <select id="s-per-page" class="select" name="general_datatable_entries_per_page" aria-describedby="s-per-page-help">
                    <?php foreach ($perPages as $option) : ?><option value="<?= (int) $option ?>"<?= $perPage === $option ? ' selected' : '' ?>><?= (int) $option ?></option><?php endforeach ?>
                </select>
                <p class="help" id="s-per-page-help"><?= esc(lang('Admin.howManyEntriesDisplay')) ?></p>
            </div>
        </div>
    </section>

    <?php if ($manages) : ?>
        <section class="panel" aria-labelledby="s-site">
            <header><h2 id="s-site"><?= esc(lang('Profile.settingsSite')) ?></h2></header>
            <div class="body">
                <label class="switch">
                    <input type="checkbox" name="two_factor" value="1"<?= $twoFactor ? ' checked' : '' ?>>
                    <span>
                        <strong><?= esc(lang('Profile.twoFactorSetting')) ?></strong>
                        <span class="muted"><?= esc(lang('Profile.twoFactorSettingText')) ?></span>
                        <span class="muted"><?= esc(lang('Profile.twoFactorRecovery')) ?></span>
                    </span>
                </label>
            </div>
        </section>
    <?php endif ?>

    <div><?= ui('button', ['label' => lang('Admin.save'), 'type' => 'submit', 'variant' => 'primary']) ?></div>
</form>
<?= $this->endSection() ?>
