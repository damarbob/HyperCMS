<?php
/**
 * The media library screen. The folders, the files and the actions are the partial `partials/browser`, which the picker
 * draws too.
 *
 * @var int $total
 */

$version = esc((string) config(\Config\Hyper::class)->appVersion, 'url');
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('head') ?>
<?php // The script registers a component for Alpine, which must happen before Alpine starts, so it goes in the head with `defer`. ?>
<script src="<?= attr(base_url('assets/ui/media.js') . '?v=' . $version) ?>" defer></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Media.moduleName')) ?></h1>
        <p><?= esc(lang('Media.pageText')) ?></p>
    </div>
</div>

<?= view('Media\Views\partials\browser', get_defined_vars()) ?>
<?= $this->endSection() ?>
