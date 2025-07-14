<?php
helper('hyper_hex');
helper('form');

$requester = hex_encode($uri);

$fieldsError = validation_show_error('fields');
?>

<div class="block">
    <form id="hyper-form" action="<?= $formAction ?>" method="POST" enctype="multipart/form-data">
        <div id="hyper-fields-container" class="field">
        </div>

        <!-- Save, history, and delete button (if applicable) -->
        <div class="field is-grouped">

            <!-- Save button -->
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
                <!-- Show history and delete button on edit page -->

                <!-- History button -->
                <div class="control is-flex-grow-1">
                    <button id="historyButton" type="button" class="button hyperHistory" title="<?= lang('Admin.entryHistory') ?>">
                        <span class="icon">
                            <i class="fas fa-clock-rotate-left"></i>
                        </span>
                    </button>
                </div>

                <!-- Delete button -->
                <div class="control">
                    <button id="deleteButton" type="button" class="button is-link is-danger hyperDelete">
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
        <!-- Add delete form on edit mode -->
        <form id="deleteForm" method="POST" action="<?= $deleteFormAction ?>">
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
</div>

<!-- Modals -->

<?php if (!empty($entry)): // For edit action
?>
    <!-- History modal -->
    <div id="historyModal" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card is-fullheight">
            <section class="modal-card-body is-flex" style="--bulma-modal-card-body-padding: 0.5rem;">
                <iframe id="historyIframe" class="is-flex-grow-1" data-src="<?= base_url('admin/entry-data/' . $entry['id']) ?>" frameborder="0"></iframe>
            </section>
        </div>
        <button class="modal-close delete is-large" aria-label="close"></button>
    </div>
<?php endif; ?>

<!-- Modal for File Manager -->
<div id="fileManagerModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card is-fullheight">
        <div class="modal-card-body is-flex" style="--bulma-modal-card-body-padding: 0.5rem;">
            <iframe id="fileManagerIframe" class="is-flex-grow-1" data-src="<?= base_url("admin/file-manager?requester_id={$requester}") ?>" frameborder="0"></iframe>
        </div>
    </div>
    <button class="modal-close is-large" aria-label="close"></button>
</div>

<?php if (ENVIRONMENT === 'testing' && $action === 'edit'): ?>
    <!-- Debugging on testing environment -->
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
                    <button type="button" class="button is-link is-danger hyperDelete"><?= lang('Admin.delete') ?></button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<?= $this->section('footer') ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= base_url('assets/App/admin/entries.js') ?>"></script>
<?= $this->endSection() ?>