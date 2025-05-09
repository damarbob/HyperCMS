<?php

/** @var App\Services\HyperHooks */
$hooks = service('hooks');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<?php
if ($action == 'edit') {
    echo $hooks->trigger(hook('Backend.view:entries:edit'), [$model, $entry]);
}
if ($action == 'new') {
    echo $hooks->trigger(hook('Backend.view:entries:new'), [$model]);
}
?>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<?= $this->include('admin/partials/entries_scripts') ?>
<?= $this->endSection() ?>