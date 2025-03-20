<?php
helper('form');

$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <div class="box">
        <form id="hyper-form" action="<?= base_url('api/test/entries/save/' . $entry['id']) ?>" method="POST" enctype="multipart/form-data">
            <div id="hyper-fields-container" class="field">
            </div>
            <div class="field is-grouped">
                <div class="control is-flex-grow-1">
                    <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
                </div>
                <div class="control">
                    <button type="button" class="button is-link is-danger" onclick="deleteModel()"><?= lang('Admin.delete') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (ENVIRONMENT == 'development'): ?>
    <div class="block">
        <form method="POST" action="<?= base_url('admin/entries/' . $entry['id']) ?>">
            <div class="field">
                <label class="label"><?= lang('Admin.fields') ?></label>
                <div class="control">
                    <textarea id="fields" name="fields" class="textarea" placeholder="<?= lang('Admin.fieldsSyntax') ?>"><?= old('fields') ?: $entry['fields'] ?></textarea>
                </div>
                <?php if ($fieldsError): ?>
                    <p class="help is-danger"><?= $fieldsError ?></p>
                <?php endif; ?>
            </div>
            <div class="field is-grouped">
                <div class="control is-flex-grow-1">
                    <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
                </div>
                <div class="control">
                    <button type="button" class="button is-link is-danger" onclick="deleteModel()"><?= lang('Admin.delete') ?></button>
                </div>
            </div>
        </form>
        <form id="deleteForm" method="POST" action="<?= base_url("admin/entries/{$entry['id']}/delete") ?>">
        </form>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('admin/entries_scripts') ?>
<?= $this->endSection() ?>