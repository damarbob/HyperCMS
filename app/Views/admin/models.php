<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<table id="hyperTable" class="table is-striped" style="width:100%">
</table>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.css" rel="stylesheet" integrity="sha384-wAbr9qEp5JojSKDr01s3gfk2usG6WR/OfpUIFEliYPzIBy5Jr9WBChdyqfWfbtt6" crossorigin="anonymous">

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.js" integrity="sha384-JYvoIYf/4ra9ifw1ESGWSNm3QVSdAuT8OaSDJLTKTkRWntshpsM1beOZKdjAXOAb" crossorigin="anonymous"></script>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script type="module" src="<?= base_url('assets/App/admin/models.js') ?>"></script>
<?= $this->endSection() ?>