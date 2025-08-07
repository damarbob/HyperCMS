<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <table id="hyperTable" class="table is-striped" style="width:100%">
    </table>
</div>

<!-- Content modal -->
<div id="contentModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card is-fullscreen">
        <section class="modal-card-body">
            <h1 class="title"><?= lang('Admin.preview') ?></h1>
            <pre id="contentArea"></pre>
        </section>
    </div>
    <button class="modal-close button is-large" aria-label="close"></button>
</div>
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
<style>
    /* (Optional) Adjust tooltip styling if needed */
    .has-tooltip-multiline[data-tooltip]::after {
        white-space: pre-wrap;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>

<!-- HTML Entity -->
<script src="https://cdn.jsdelivr.net/npm/he@1.2.0/he.min.js"></script>

<script type="text/javascript">
    function confirmSelectedData() {
        var selectedRows = hyperTable.rows({
            selected: true
        }).data().toArray();
        if (selectedRows.length > 0) {
            // Post the message with the deserialized data included
            window.parent.postMessage({
                action: 'entryDataSelected',
                data: selectedRows
            }, '<?= base_url() ?>');
        }
    }

    function openPreviewModal(content) {
        // Set the content in the modal's content area
        const contentArea = document.getElementById('contentArea');
        if (contentArea) {
            contentArea.innerHTML = he.encode(unescape(content));
        }
        // Get the modal element (you can also pass this in if desired)
        const modal = document.getElementById('contentModal');
        // Now open the modal using your openModal function (make sure it's defined)
        openModal(modal);
    }
</script>
<script type="module" src="<?= base_url('assets/App/admin/entry_data.js') ?>"></script>
<?= $this->endSection() ?>