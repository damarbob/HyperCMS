<?php
helper('hyper_text');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<table id="hyperTable" class="table is-striped" style="width:100%">
</table>

<!-- New entry modal -->
<div id="newModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon mr-4">
                    <i class="fa-solid fa-plus"></i>
                </span>
                <span>
                    <?= lang('Admin.newEntry') ?>
                </span>
            </p>
            <button class="delete" aria-label="close"></button>
        </header>
        <section class="modal-card-body">
            <div id="users">
                <div class="field has-addons">
                    <div class="control">
                        <input class="search input is-small" type="text" placeholder=lang.search>
                    </div>
                    <div class="control">
                        <button class="sort button is-small" data-sort="model-name">
                            <?= lang('Admin.sortByName') ?>
                        </button>
                    </div>
                </div>
                <div class="menu">
                    <ul class="list menu-list">
                        <?php foreach ($models as $model) : ?>
                            <li>
                                <a href="<?= replace_placeholders($links['new'], ['id' => $model['id']]) ?>" class="is-flex is-gap-1">
                                    <span class="icon">
                                        <i class="<?= !empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open' ?>"></i>
                                    </span>
                                    <span class="model-name is-flex-grow-1">
                                        <?= lang('Admin.newx', ['x' => $model['name']]) ?>
                                    </span>
                                    <span class="icon">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    </ul>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <div class="buttons">
                <button class="button dismiss"><?= lang('Admin.cancel') ?></button>
            </div>
        </footer>
    </div>
</div>

<!-- Filter modal -->
<div id="filterModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon mr-4">
                    <i class="fa-solid fa-filter"></i>
                </span>
                <span>
                    <?= lang('Admin.filter') ?>
                </span>
            </p>
            <!-- The delete button has a "modal-close" class so that it can be closed via JS -->
            <button class="delete" aria-label="close"></button>
        </header>
        <section class="modal-card-body">
            <div id="filterModels">
                <h2 class="subtitle"><?= lang('Admin.models') ?></h2>
                <div class="buttons">
                    <!-- A button to remove filtering and show "all" models -->
                    <button type="button" class="button is-light filter-model-reset">
                        <?= lang('Admin.all') ?>
                    </button>
                    <?php foreach ($models as $model): ?>
                        <button type="button"
                            class="button filter-model-btn"
                            data-model-id="<?= $model['id'] ?>">
                            <span class="icon">
                                <i class="<?= !empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open' ?>"></i>
                            </span>
                            <span>
                                <?= $model['name'] ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button type="button" class="button dismiss"><?= lang('Admin.cancel') ?></button>
        </footer>
    </div>
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
<?= $this->endSection() ?>

<?= $this->section('footer') ?>

<!-- HTML Entity -->
<script src="https://cdn.jsdelivr.net/npm/he@1.2.0/he.min.js"></script>

<!-- List.js -->
<script src="//cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>

<script>
    /* Initialize List.js */
    var options = {
        valueNames: ['model-name']
    };

    var userList = new List('users', options);
</script>

<script type="module" src="<?= base_url('assets/App/admin/entries_table.js'); ?>"></script>
<?= $this->endSection() ?>