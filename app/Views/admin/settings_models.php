<?php
helper('form');

$context = 'user:' . user_id();
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <h1 class="title">
        <?= lang('admin.settings') ?>
    </h1>
    <p class="subtitle">
        <?= lang('admin.models') ?>
    </p>
    <!-- Empty trash button -->
    <div class="field">
        <label class="label"><?= lang('Admin.emptyModelsTrash') ?></label>
        <div class="control is-flex-grow-1">
            <button id="buttonSettingsEmptyTrash" class="button is-danger">
                <span class="icon">
                    <i class="fa-solid fa-face-tired"></i>
                </span>
                <span>
                    <?= lang('Admin.emptyTrash') ?>
                </span>
            </button>
        </div>
        <p class="help"><?= lang('Admin.emptyingTheModelsTrashWillPermanentlyDelete') ?></p>
    </div>
</div>

<!-- Empty trash form -->
<form id="formEmptyTrash" method="POST" action="<?= base_url('admin/models/purge-deleted') ?>">
    <?= csrf_field() ?>
</form>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script type="text/javascript">
    // CSRF
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';

    document.addEventListener('DOMContentLoaded', function() {
        var button = document.getElementById('buttonSettingsEmptyTrash');
        button.addEventListener('click', function(e) {
            e.preventDefault();

            window.hyper.factory.swal.confirm().then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formEmptyTrash').submit();
                }
            });
        });
    });
</script>
<?= $this->endSection() ?>