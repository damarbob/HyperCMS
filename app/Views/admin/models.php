<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<table id="hyperTable" class="table is-striped" style="width:100%">
</table>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="<?= base_url('assets/vendor/datatables.net-bm/css/dataTables.bulma.css') ?>" rel="stylesheet">
<link href="<?= base_url('assets/vendor/datatables.net-buttons-bm/css/buttons.bulma.css') ?>" rel="stylesheet">
<link href="<?= base_url('assets/vendor/datatables.net-colreorder-bm/css/colReorder.bulma.css') ?>" rel="stylesheet">
<link href="<?= base_url('assets/vendor/datatables.net-fixedheader-bm/css/fixedHeader.bulma.css') ?>" rel="stylesheet">
<link href="<?= base_url('assets/vendor/datatables.net-responsive-bm/css/responsive.bulma.css') ?>" rel="stylesheet">
<link href="<?= base_url('assets/vendor/datatables.net-select-bm/css/select.bulma.css') ?>" rel="stylesheet">

<script src="<?= base_url('assets/vendor/jquery/dist/jquery.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/jszip/dist/jszip.js') ?>"></script>
<script src="<?= base_url('assets/vendor/pdfmake/build/pdfmake.js') ?>"></script>
<script src="<?= base_url('assets/vendor/pdfmake/build/vfs_fonts.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net/js/dataTables.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-bm/js/dataTables.bulma.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-buttons/js/dataTables.buttons.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-buttons-bm/js/buttons.bulma.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-buttons/js/buttons.colVis.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-buttons/js/buttons.html5.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-buttons/js/buttons.print.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-colreorder/js/dataTables.colReorder.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-fixedheader/js/dataTables.fixedHeader.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-responsive/js/dataTables.responsive.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-responsive-bm/js/responsive.bulma.js') ?>"></script>
<script src="<?= base_url('assets/vendor/datatables.net-select/js/dataTables.select.js') ?>"></script>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script type="module" src="<?= base_url('assets/App/admin/models.js') ?>"></script>
<?= $this->endSection() ?>