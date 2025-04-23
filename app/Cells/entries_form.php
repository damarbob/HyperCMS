<?php
helper('form');

$fieldsError = validation_show_error('fields');

$action = $type === 'edit' ? base_url('api/test/entries/save/' . $entry['id']) : base_url('api/test/entries/create/' . $model['id']);
?>

<div class="block">
    <form id="hyper-form" action="<?= $action ?>" method="POST" enctype="multipart/form-data">
        <div id="hyper-fields-container" class="field">
        </div>
        <div class="field is-grouped">
            <div class="control is-flex-grow-1">
                <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
            </div>
            <?php if ($type === 'edit'): ?>
                <!-- Show delete button on edit page -->
                <div class="control">
                    <button type="button" class="button is-link is-danger" onclick="deleteModel()"><?= lang('Admin.delete') ?></button>
                </div>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($type === 'edit'): ?>
        <form id="deleteForm" method="POST" action="<?= base_url("admin/entries/{$entry['id']}/delete") ?>">
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
</div>

<?php if (ENVIRONMENT !== 'production' && $type === 'edit'): ?>
    <!-- Debugging -->
    <div class="block">
        <form method="POST" action="<?= $action ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="label"><?= lang('Admin.fields') ?></label>
                <div class="control">
                    <textarea id="fields" name="fields" class="textarea" placeholder="<?= lang('Admin.fieldsSyntax') ?>"><?= old('fields') ?: ($entry['fields'] ?? '') ?></textarea>
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
    </div>
<?php endif; ?>