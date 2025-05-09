<?php
helper('form');

$fieldsError = validation_show_error('fields');
?>

<div class="block">
    <form id="hyper-form" action="<?= $formAction ?>" method="POST" enctype="multipart/form-data">
        <div id="hyper-fields-container" class="field">
        </div>
        <div class="field is-grouped">
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
                <div class="control is-flex-grow-1">
                    <button type="button" class="button" onclick="showHistoryModal()" title="<?= lang('Admin.entryHistory') ?>">
                        <span class="icon">
                            <i class="fas fa-clock-rotate-left"></i>
                        </span>
                    </button>
                </div>
            <?php endif; ?>
            <?php if ($action === 'edit'): ?>
                <!-- Show delete button on edit page -->
                <div class="control">
                    <button type="button" class="button is-link is-danger" onclick="deleteEntry()">
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
        <form id="deleteForm" method="POST" action="<?= base_url("admin/entries/{$entry['id']}/delete") ?>">
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
</div>

<?php if (ENVIRONMENT === 'testing' && $action === 'edit'): ?>
    <!-- Debugging -->
    <div class="block">
        <form method="POST" action="<?= $formAction ?>">
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
                    <button type="submit" class="button is-primary">
                        <span class="icon">
                            <i class="fas fa-check"></i>
                        </span>
                        <span>
                            <?= lang('Admin.save') ?>
                        </span>
                    </button>
                </div>
                <div class="control">
                    <button type="button" class="button is-link is-danger" onclick="deleteModel()"><?= lang('Admin.delete') ?></button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>