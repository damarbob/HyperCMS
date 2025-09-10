<?= $this->extend('admin/layout/page') ?>

<?= $this->section('head') ?>

<!-- Datatables -->
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
<!-- End of datatables -->

<!-- Dropzone -->
<script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css">

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Codicon for Monaco Editor -->
<link href="https://cdn.jsdelivr.net/npm/vscode-codicons@0.0.17/dist/codicon.min.css" rel="stylesheet">

<link rel="stylesheet" href="<?= base_url('assets/App/admin/file_manager.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div>
    <!-- Loader -->
    <div id="loaderBody" class="loader-overlay">
        <span class="loader-body"></span>
        <span class="is-sr-only"><?= lang('Admin.loading') ?></span>
    </div>
    <!-- Toolbar -->
    <div class="pb-3" style="background-color: var(--bulma-scheme-main);">
        <div class="field is-grouped is-grouped-multiline">

            <!-- Iframe specific -->
            <div class="control is-in-iframe">
                <button class="button is-primary" onclick="confirmSelectedFiles()">
                    <span class="icon">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <span>
                        <?= lang('Admin.select') ?>
                    </span>
                </button>
            </div>

            <!-- Upload Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button is-primary" onclick="toggleDropzone()">
                        <span class="icon">
                            <i class="fas fa-upload"></i>
                        </span>
                        <span><?= lang('Admin.upload') ?></span>
                    </button>
                    <button class="button is-primary" onclick="window.hyper.factory.fileManager.refreshFileList()" data-tippy-content="<?= lang('Admin.refresh') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-arrows-rotate"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Create Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="window.hyper.factory.fileManager.createFile()" data-tippy-content="<?= lang('Admin.createNewFile') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-file-circle-plus"></i>
                        </span>
                    </button>
                    <button class="button" onclick="window.hyper.factory.fileManager.createFolder()" data-tippy-content="<?= lang('Admin.createNewFolder') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-folder-plus"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Copy/Move Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="window.hyper.factory.fileManager.copySelectedFiles()" data-tippy-content="<?= lang('Admin.copySelected') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-copy"></i>
                        </span>
                    </button>
                    <button class="button is-warning" onclick="window.hyper.factory.fileManager.moveSelectedFiles()" data-tippy-content="<?= lang('Admin.moveSelected') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-scissors"></i>
                        </span>
                    </button>
                    <button class="button" onclick="window.hyper.factory.fileManager.pasteFiles()" data-tippy-content="<?= lang('Admin.paste') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-paste"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Archive Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="window.hyper.factory.fileManager.extractSelectedFiles()" data-tippy-content="<?= lang('Admin.extractZipFile') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-box-open"></i>
                        </span>
                    </button>
                    <button class="button" onclick="window.hyper.factory.fileManager.compressSelectedFiles()" data-tippy-content="<?= lang('Admin.compressToZip') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-file-zipper"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Delete Group -->
            <div class="control">
                <button class="button is-danger" onclick="window.hyper.factory.fileManager.deleteSelectedFiles()" data-tippy-content="<?= lang('Admin.deleteSelected') ?>" data-tippy-placement="bottom">
                    <span class="icon">
                        <i class="fas fa-trash"></i>
                    </span>
                </button>
            </div>
        </div>

        <!-- Dropzone Container -->
        <div id="dropzoneContainer" class="box mt-3" style="display: none;">
            <form action="<?= base_url('admin/api/file-manager/upload') ?>" class="dropzone" id="fileDropzone" style="border: none; background: none;"></form>
            <p id="uploadProgress" style="display: none;"><?= lang('Admin.uploadingFile') ?></p>
        </div>
    </div>

    <!-- File Table -->
    <table id="hyperTable" class="table is-hoverable is-fullwidth">
        <thead>
            <tr>
                <th>
                    <span>
                        <i class="fa-regular fa-bookmark"></i>
                    </span>
                </th>
                <th><?= lang('Admin.name') ?></th>
                <th><?= lang('Admin.size') ?></th>
                <th><?= lang('Admin.permission') ?></th>
                <th><?= lang('Admin.dateModified') ?></th>
                <th><?= lang('Admin.action') ?></th>
            </tr>
        </thead>
        <tbody id="fileList">
            <!-- Dynamic File List here -->
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal" id="viewModal">
    <div class="modal-background"></div>
    <div class="modal-card is-large">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span id="viewModalLabel"><?= lang('Admin.title') ?></span>
                <span id="loaderModal" class="loader is-small ml-2 mr-2" style="display: none;"></span>
            </p>
            <button class="delete" aria-label="close"></button>
        </header>
        <section class="modal-card-body">
            <div id="viewModalKonten">...</div>
            <div id="monaco" class="is-hidden" style="height: 512px;"></div>
            <textarea id="fileEditor" class="textarea is-hidden" rows="10"></textarea>
        </section>
        <footer class="modal-card-foot is-justify-content-space-between">

            <!-- Cancel button -->
            <button class="button is-light dismiss">
                <span class="icon">
                    <i class="fas fa-chevron-left"></i>
                </span>
                <span><?= lang('Admin.cancel') ?></span>
            </button>

            <!-- Select button -->
            <button class="button is-primary is-in-iframe dismiss" onclick="confirmCurrentFile()">
                <span class="icon">
                    <i class="fas fa-check"></i>
                </span>
                <span><?= lang('Admin.select') ?></span>
            </button>

            <!-- Save button -->
            <button id="saveButton" class="button is-primary" style="display: none;">
                <span class="icon">
                    <i class="fas fa-save"></i>
                </span>
                <span><?= lang('Admin.save') ?></span>
            </button>
        </footer>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<!-- TODO: Convert to module -->
<script type="text/javascript" src="<?= base_url('assets/App/admin/file_manager.js') ?>"></script>
<?= $this->endSection() ?>