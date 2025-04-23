<?php

/** @var App\Services\HyperHooks */
$hooks = service('hooks');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<?= $hooks->trigger(hook('backend.view:entries:new'), [$model]) ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('admin/entries_scripts') ?>
<?= $this->endSection() ?>