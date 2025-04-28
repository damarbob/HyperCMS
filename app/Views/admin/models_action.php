<?php
helper('form');

$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<form id="formEditModel" method="POST" action="<?= $formAction ?>">
    <?= csrf_field() ?>
    <div class="field">
        <label class="label"><?= lang('Admin.name') ?></label>
        <div class="control has-icons-right">
            <input class="input <?= ($nameError) ? 'is-danger' : '' ?>" type="text" placeholder="<?= lang('Admin.name') ?>" name="name" value="<?= old('name') ?: (!empty($model['name']) ? $model['name'] : '') ?>" />
            <?php if ($nameError): ?>
                <span class="icon is-small is-right"><i class="fas fa-exclamation-triangle"></i></span>
            <?php endif; ?>
        </div>
        <p class="help"><?= lang('Admin.giveModelName') ?></p>
        <?php if ($nameError): ?>
            <p class="help is-danger"><?= $nameError ?></p>
        <?php endif; ?>
    </div>
    <div class="field">
        <label class="label"><?= lang('Admin.fields') ?></label>
        <div class="control">
            <div id="monaco" class="mb-4" style="height: 512px;">
            </div>
            <textarea id="fields" name="fields" class="textarea is-hidden" placeholder="<?= lang('Admin.fieldsSyntax') ?>"><?= old('fields') ?: (!empty($model['fields']) ? $model['fields'] : '') ?></textarea>
        </div>
        <p class="help"><?= lang('Admin.enterModelFieldsInJsonFormat') ?></p>
        <p class="help"><?= lang('Admin.xToFormatAndValidateJson', ['x' => '<b>Ctrl + B</b>']) ?></p>
        <p class="help"><?= lang('Admin.xToToggleFullscreenMode', ['x' => '<b>Ctrl + Alt + Z</b>']) ?></p>
        <?php if ($fieldsError): ?>
            <p class="help is-danger"><?= $fieldsError ?></p>
        <?php endif; ?>
    </div>
    <div class="field">
        <label class="label"><?= lang('Admin.icon') ?></label>
        <div class="control has-icons-left">
            <span class="icon is-small is-small"><i id="iconPreview" class="fa-solid fa-box-open"></i></span>
            <input id="iconInput" class="input" oninput="updateIconPreview(this)" type="text" placeholder="<?= lang('Admin.icon') ?>" name="icon" value="<?= old('icon') ?: (!empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open') ?>" />
        </div>
        <p class="help">
            <?= lang('Admin.forCompleteListOfIconsPleaseVisitx', ['x' => '<a href="https://fontawesome.com/icons" target="_blank">Font Awesome</a> version 6.7.2']) ?>
        </p>
    </div>
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
        <?php if ($action === 'edit'): ?>
            <!-- Show delete button on edit page -->
            <div class="control">
                <button type="button" class="button is-link is-danger" onclick="deleteModel()">
                    <span class="icon">
                        <i class="fas fa-trash"></i>
                    </span>
                    <span>
                        <?= lang('Admin.delete') ?>
                    </span>
                </button>
            </div>
        <?php endif; ?>
    </div>
</form>
<?php if ($action === 'edit'): ?>
    <form id="deleteForm" method="POST" action="<?= base_url("admin/models/{$model['id']}/delete") ?>">
        <?= csrf_field() ?>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const iconInput = document.querySelector('#iconInput');
        if (iconInput) {
            updateIconPreview(iconInput);
        }
    });

    function updateIconPreview(iconInput) {
        const iconPreview = document.querySelector('#iconPreview');
        if (iconInput && iconPreview) {
            iconPreview.className = iconInput.value;
        }
    }

    function deleteModel() {
        window.hyper_swal.confirm({
            title: "<?= lang('Admin.areYouSure') ?>",
            text: "<?= lang('Admin.youWillNotBeAbleToRevertThis') ?>",
            confirmButtonColor: "var(--bulma-danger)",
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
        textareaId: "fields",
        language: "json",
        onSave: function(editor) {
            const form = document.getElementById('formEditModel');
            if (form) {
                form.submit();
            } else {
                console.warn("Form id is not assigned to the editor");
            }
        },
    });
</script>
<?= $this->endSection() ?>