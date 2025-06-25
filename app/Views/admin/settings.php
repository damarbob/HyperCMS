<?php
helper('form');

$context = 'user:' . user_id();

$datatableEntriesPerPageValue = service('settings')->get('App.datatableEntriesPerPage', $context);
$datatableEntriesPerPageOptions = [10, 25, 50, 100];
$datatableEntriesPerPageError = validation_show_error('general_datatable_entries_per_page');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<?= service('hooks')->trigger(hook('Backend.view:settings')) ?>
<div class="block">
    <h1 class="title">
        <?= lang('Admin.settings') ?>
    </h1>
    <p class="subtitle">
        <?= lang('Admin.general') ?>
    </p>
    <form action="<?= base_url('admin/settings/update') ?>" method="POST">
        <?= csrf_field() ?>

        <!-- Primary model selection -->
        <div class="field">
            <label class="label"><?= lang('Admin.datatableEntriesPerPage') ?></label>
            <div class="control">
                <div class="select">
                    <select name="general_datatable_entries_per_page" value="<?= $datatableEntriesPerPageValue ?>">
                        <?php for ($i = 0; $i < count($datatableEntriesPerPageOptions); $i++): ?>
                            <option value="<?= $datatableEntriesPerPageOptions[$i] ?>" <?= ($datatableEntriesPerPageValue == $datatableEntriesPerPageOptions[$i]) ? 'selected' : '' ?>><?= $datatableEntriesPerPageOptions[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <p class="help"><?= lang('Admin.howManyEntriesDisplay') ?></p>
            <?php if ($datatableEntriesPerPageError): ?>
                <p class="help is-danger"><?= $datatableEntriesPerPageError ?></p>
            <?php endif; ?>
        </div>

        <!-- Submit button -->
        <div class="field is-grouped">
            <div class="control is-flex-grow-1">
                <button type="submit" class="button is-primary">
                    <span class="icon">
                        <i class="fas fa-check"></i>
                    </span>
                    <span>
                        <?= lang('Admin.save') ?>
                    </span>
                </button>
            </div>
        </div>

    </form>
</div>
<?= $this->endSection() ?>