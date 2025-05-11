<?php
helper('form');

$nameError = validation_show_error('name');
$fieldsError = validation_show_error('fields');
?>
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

<?= $this->section('footer') ?>
<?= $this->include('admin/partials/models_scripts') ?>
<?= $this->endSection() ?>