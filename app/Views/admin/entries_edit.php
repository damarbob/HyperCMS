<?php

/** @var App\Services\HyperHooks */
$hooks = service('hooks');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<?= $hooks->trigger(hook('backend.view:entries:edit'), [$model, $entry]) ?>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<?= $this->include('admin/partials/entries_scripts') ?>
<?= $this->endSection() ?>