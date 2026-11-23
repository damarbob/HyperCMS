<?= $this->extend('admin/layout/app') ?>

<?= $this->section('scripts') ?>
<script src="<?= attr(base_url('assets/ui/islands.js') . '?v=' . esc((string) config(\Config\Hyper::class)->appVersion, 'url')) ?>" defer></script>
<script src="<?= attr(base_url('assets/ui/picker.js') . '?v=' . esc((string) config(\Config\Hyper::class)->appVersion, 'url')) ?>" defer></script>
<script src="<?= attr(base_url('assets/ui/media.js') . '?v=' . esc((string) config(\Config\Hyper::class)->appVersion, 'url')) ?>" defer></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('admin/partials/entry_form', get_defined_vars()) ?>
<?= $this->endSection() ?>
