<?php
helper('form');

$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<form id="formEditModel" method="POST" action="<?= $formAction ?>">
    <?= csrf_field() ?>

    <!-- Name -->
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

    <!-- Fields -->
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

    <!-- Icon -->
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

    <!-- Button field group -->
    <div class="field is-grouped">

        <!-- Submit -->
        <div class="control">
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
            <!-- Show history button -->
            <div class="control is-flex-grow-1">
                <button type="button" class="button" onclick="showHistoryModal()" title="<?= lang('Admin.modelxHistory', ['x' => $model['name']]) ?>">
                    <span class="icon">
                        <i class="fas fa-clock-rotate-left"></i>
                    </span>
                </button>
            </div>
        <?php endif; ?>

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
    <!-- End of button field group -->

</form>
<?php if ($action === 'edit'): ?>
    <form id="deleteForm" method="POST" action="<?= base_url("admin/models/{$model['id']}/delete") ?>">
        <?= csrf_field() ?>
    </form>
<?php endif; ?>
<?php if ($action === 'edit'): ?>
    <!-- History modal -->
    <div id="historyModal" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card is-fullheight">
            <section class="modal-card-body is-flex" style="--bulma-modal-card-body-padding: 0.5rem;">
                <iframe id="historyIframe" class="is-flex-grow-1" data-src="<?= base_url('admin/model-data/' . $model['id']) ?>" frameborder="0"></iframe>
            </section>
        </div>
        <button class="modal-close delete is-large" aria-label="close"></button>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script>
    // Listen for messages from the file manager. (Attach this listener globally if needed.)
    window.addEventListener('message', function(event) {

        // Validate event.origin for extra security.
        if (!window.hyper_areUrisEqual(event.origin, '<?= base_url() ?>')) return;

        if (event.data && event.data.action === 'modelDataSelected') {
            const selectedData = event.data.data; // Array of URL strings.
            useData(selectedData);
            // Close the modal after processing the selection.
            closeModal(document.getElementById('historyModal'));
        }
    });

    function useData(selectedData) {
        // Check if any rows are selected
        if (selectedData.length > 0) {
            // Get the first selected row's ID and model name
            let name = selectedData[0].name;
            let fields = selectedData[0].fields;
            let icon = selectedData[0].icon;

            console.log(name, fields, icon);

            document.getElementsByName('name').forEach(e => {
                e.value = name;
            });

            document.getElementsByName('fields').forEach(e => {
                e.value = fields;
                e.dispatchEvent(new Event('change'));
            });

            document.getElementsByName('icon').forEach(e => {
                e.innerHTML = icon;
            });

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

            window.hyper_swal.success("<?= lang('Admin.success') ?>");

        } else {
            window.hyper_swal.error("<?= lang('Admin.selectRow') ?>");
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const iconInput = document.querySelector('#iconInput');
        if (iconInput) {
            updateIconPreview(iconInput);
        }
    });

    function showHistoryModal() {
        // Open the history modal.
        openModal(document.getElementById('historyModal'));

        // Lazy load the iframe source if it hasn't been loaded already.
        const iframe = document.getElementById('historyIframe');
        if (!iframe.getAttribute('src')) {
            iframe.setAttribute('src', iframe.getAttribute('data-src'));
        }
    }

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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const myEditor = window.hyper_monaco({
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
    });
</script>
<?= $this->endSection() ?>