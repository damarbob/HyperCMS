<?php
/**
 * The abilities of the admin, and a form to add one by name.
 *
 * @var list<array<string, mixed>> $columns
 * @var list<array<string, mixed>> $rows
 * @var string                     $error
 * @var array<string, string>      $values
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Roles.abTitle')) ?></h1>
        <p><?= esc(lang('Roles.abText')) ?></p>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('Roles.title'), 'href' => base_url('admin/roles'), 'icon' => 'users']) ?>
    </div>
</div>

<?= ui('table', ['caption' => lang('Roles.abTitle'), 'columns' => $columns, 'rows' => $rows]) ?>

<section class="panel" aria-labelledby="ability-add" style="margin-top: var(--s5); max-width: 760px">
    <header><h2 id="ability-add"><?= esc(lang('Roles.abAdd')) ?></h2></header>
    <form class="body stack" method="post" action="<?= attr(base_url('admin/abilities')) ?>">
        <?= csrf_field() ?>
        <?php if ($error !== '') : ?>
            <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($error) ?></span></div>
        <?php endif ?>
        <div class="details-grid" style="max-width: none">
            <div class="field">
                <label for="a-name"><?= esc(lang('Roles.abName')) ?><span class="req" aria-hidden="true">*</span></label>
                <input id="a-name" class="input mono" type="text" name="name" value="<?= attr($values['name']) ?>" required autocomplete="off" spellcheck="false" aria-describedby="a-name-help">
                <p class="help" id="a-name-help"><?= esc(lang('Roles.abNameHelp')) ?></p>
            </div>
            <div class="field">
                <label for="a-label"><?= esc(lang('Roles.abLabel')) ?><span class="req" aria-hidden="true">*</span></label>
                <input id="a-label" class="input" type="text" name="label" value="<?= attr($values['label']) ?>" required autocomplete="off">
            </div>
            <div class="field wide">
                <label for="a-description"><?= esc(lang('Roles.abDescription')) ?></label>
                <input id="a-description" class="input" type="text" name="description" value="<?= attr($values['description']) ?>" autocomplete="off">
            </div>
        </div>
        <div><?= ui('button', ['label' => lang('Roles.abCreate'), 'type' => 'submit', 'variant' => 'primary', 'icon' => 'plus']) ?></div>
    </form>
</section>
<?= $this->endSection() ?>
