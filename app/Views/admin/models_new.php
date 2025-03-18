<?php
helper('form');

$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<form id="formNewModel" method="POST" action="<?= base_url('admin/models') ?>">
    <div class="field">
        <div class="control has-icons-right">
            <input class="input <?= ($nameError) ? 'is-danger' : '' ?>" type="text" placeholder="Name" name="name" value="<?= old('name') ?>" />
            <?php if ($nameError): ?>
                <span class="icon is-small is-right"><i class="fas fa-exclamation-triangle"></i></span>
            <?php endif; ?>
        </div>
        <?php if ($nameError): ?>
            <p class="help is-danger"><?= $nameError ?></p>
        <?php endif; ?>
    </div>
    <div class="field">
        <label class="label"><?= lang('Admin.fields') ?></label>
        <div class="control">
            <div id="monaco" class="mb-4" style="height: 512px;">
            </div>
            <textarea id="fields" name="fields" class="textarea is-hidden" placeholder="<?= lang('Admin.fieldsSyntax') ?>"><?= old('fields') ?></textarea>
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
<?= $this->endSection() ?>
<?= $this->section('head') ?>
<script type="module">
    import MonacoEditorWrapper from '<?= base_url('assets/js/admin/MonacoEditorWrapper.js') ?>';

    const myEditor = new MonacoEditorWrapper({
        editorContainerId: "monaco",
        fieldsId: "fields",
        formId: "formNewModel",
        language: "json",
    });
</script>
<?= $this->endSection() ?>