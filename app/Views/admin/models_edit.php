<?php
helper('form');
// dd($model);
$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<form id="formEditModel" method="POST" action="<?= base_url('admin/models/' . $model['id']) ?>">
    <div class="field">
        <div class="control has-icons-right">
            <input class="input <?= ($nameError) ? 'is-danger' : '' ?>" type="text" placeholder="Name" name="name" value="<?= old('name') ?: $model['name'] ?>" />
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
            <textarea id="fields" name="fields" class="textarea is-hidden" placeholder="<?= lang('Admin.fieldsSyntax') ?>"><?= old('fields') ?: $model['fields'] ?></textarea>
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
<form id="deleteForm" method="POST" action="<?= base_url("admin/models/{$model['id']}/delete") ?>">
</form>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<script>
    function deleteModel() {
        Swal.fire({
            title: "<?= lang('Admin.areYouSure') ?>",
            text: "<?= lang('Admin.youWillNotBeAbleToRevertThis') ?>",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "var(--bulma-danger)",
            // cancelButtonColor: "#d33",
            confirmButtonText: "<?= lang('Admin.yes') ?>",
            cancelButtonText: "<?= lang('Admin.cancel') ?>",
            theme: window.isDarkMode ? 'dark' : 'light',
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<script type="module">
    import MonacoEditorWrapper from '<?= base_url('assets/js/admin/MonacoEditorWrapper.js') ?>';

    const myEditor = new MonacoEditorWrapper({
        editorContainerId: "monaco",
        fieldsId: "fields",
        formId: "formEditModel",
        language: "json",
    });
</script>
<?= $this->endSection() ?>