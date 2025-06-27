<?= $this->extend('admin/layout/page') ?>

<?= $this->section('contentNoWrapper') ?>
<div id="chatContainer"></div>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= module_assets_url('Voltic', 'styles/voltic.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script type="module" src="<?= module_assets_url('Voltic', 'main.js') ?>">
</script>
<?= $this->endSection() ?>