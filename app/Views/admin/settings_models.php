<?php
/**
 * The settings of the models. For now the one thing that belongs here is emptying the models trash.
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Admin.settings')) ?></h1>
        <p><?= esc(lang('Models.settingsText')) ?></p>
    </div>
</div>

<section class="panel" aria-labelledby="s-trash" style="max-width: 720px">
    <header><h2 id="s-trash"><?= esc(lang('Models.settingsTrash')) ?></h2></header>
    <div class="body stack" style="gap: var(--s3)">
        <p class="muted"><?= esc(lang('Models.settingsTrashText')) ?></p>
        <?php // Without scripts the form is posted and the person lands on the list. With them the answer is a toast. ?>
        <form method="post" action="<?= attr(base_url('admin/models/purge-deleted')) ?>" hx-post="<?= attr(base_url('admin/models/purge-deleted')) ?>" hx-swap="none" hx-confirm="<?= attr(lang('Models.confirmEmptyTrash')) ?>">
            <?= csrf_field() ?>
            <?= ui('button', ['label' => lang('Models.emptyTrash'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
