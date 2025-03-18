<?php
helper('form');

$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <div class="box">
        <form id="hyper-form" action="<?= base_url("api/test/entries/create/" . $model['id']) ?>" method="POST" enctype="multipart/form-data">
            <div id="hyper-fields-container" class="field">
            </div>
            <div class="field is-grouped">
                <div class="control is-flex-grow-1">
                    <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (false): ?>
    <div class="block">
        <form method="POST" action="<?= base_url('admin/entries') ?>">
            <input type="hidden" name="model_id" value="<?= $model['id'] ?>" />
            <div class="field">
                <label class="label"><?= lang('Admin.fields') ?></label>
                <div class="control">
                    <textarea id="fields" name="fields" class="textarea" placeholder="<?= lang('Admin.fieldsSyntax') ?>"><?= old('fields') ?></textarea>
                </div>
                <?php if ($fieldsError): ?>
                    <p class="help is-danger"><?= $fieldsError ?></p>
                <?php endif; ?>
            </div>
            <div class="field is-grouped">
                <div class="control">
                    <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('admin/entries_scripts') ?>
<?= $this->endSection() ?>