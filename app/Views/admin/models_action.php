<?php
$hooks = service('hooks');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<?php
if ($action == 'edit') {
    echo $hooks->trigger(hook('Backend.view:models:edit'), [$model]);
}
if ($action == 'new') {
    echo $hooks->trigger(hook('Backend.view:models:new'));
}
?>
<?= $this->endSection() ?>
