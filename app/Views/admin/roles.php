<?php
/**
 * The roles of the admin.
 *
 * @var int                        $count
 * @var list<array<string, mixed>> $columns
 * @var list<array<string, mixed>> $rows
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Roles.title')) ?></h1>
        <p><?= esc(lang('Roles.text')) ?></p>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('Roles.abTitle'), 'href' => base_url('admin/abilities'), 'icon' => 'key']) ?>
        <?= ui('button', ['label' => lang('Roles.newRole'), 'href' => base_url('admin/roles/new'), 'variant' => 'primary', 'icon' => 'plus']) ?>
    </div>
</div>

<?= ui('table', ['caption' => lang('Roles.title'), 'columns' => $columns, 'rows' => $rows]) ?>
<?= $this->endSection() ?>
