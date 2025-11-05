<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <table id="usersTable" class="table is-fullwidth">
        <thead>
            <tr>
                <th>#</th>
                <th><?= lang('Auth.username') ?></th>
                <th><?= lang('Auth.email') ?></th>
                <th><?= lang('UserManagement.groups') ?></th>
            </tr>
        </thead>
    </table>
</div>

<!-- User Modal -->
<div class="modal" id="userModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title"><?= lang('UserManagement.userForm') ?></p>
            <button class="delete"></button>
        </header>

        <form id="userForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="userId">

            <div class="modal-card-body">

                <!-- Username -->
                <div class="field">
                    <label class="label"><?= lang('Auth.username') ?></label>
                    <div class="control">
                        <input class="input" type="text" name="username" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="field">
                    <label class="label"><?= lang('Auth.email') ?></label>
                    <div class="control">
                        <input class="input" type="email" name="email" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="field">
                    <label class="label"><?= lang('Auth.password') ?></label>
                    <div class="control">
                        <input class="input" type="password" name="password" id="password" value="">
                        <p class="help"><?= lang('UserManagement.leaveBlankToKeepCurrentPassword') ?></p>
                    </div>
                </div>

                <!-- Groups -->
                <div class="field">
                    <label class="label"><?= lang('UserManagement.groups') ?></label>
                    <div class="checkboxes">
                        <?php foreach ($groups as $group => $config): ?>
                            <label class="checkbox">
                                <input type="checkbox" name="groups[]" value="<?= $group ?>">
                                <?= ucfirst($group) ?>
                            </label>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <footer class="modal-card-foot">
                <div class="buttons">
                    <!-- Submit -->
                    <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
                    <!-- Cancel -->
                    <button type="button" class="button dismiss"><?= lang('Admin.cancel') ?></button>
                </div>
            </footer>
        </form>
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
<script type="text/javascript" src="<?= module_assets_url('UserManagement', 'users.js') ?>"></script>
<?= $this->endSection() ?>