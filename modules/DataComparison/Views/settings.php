<?php
helper('form');

$dataSourcesError = validation_show_error('dataSources');
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <h1 class="title">
        <?= lang('Dc.moduleName') ?>
    </h1>
    <p class="subtitle">
        <?= lang('Dc.moduleDescription') ?>
    </p>

    <form id="formDcSettings" action="<?= base_url('admin/settings/data-comparison/update') ?>" method="POST">
        <?= csrf_field() ?>

        <!-- Data sources -->
        <div class="field">
            <label class="label"><?= lang('Dc.dataSource') ?></label>
            <div class="control">
                <div id="monaco" class="mb-4" style="height: 512px;">
                </div>
                <textarea id="dataSources" name="dataSources" class="textarea is-hidden" placeholder="<?= lang('Dc.dataSources') ?>"><?= old('dataSources') ?: (!empty($dataSources) ? $dataSources : '') ?></textarea>
            </div>
            <p class="help"><?= lang('Dc.enterDataSourcesInJsonFormat') ?></p>
            <p class="help"><?= lang('Admin.xToFormatAndValidateJson', ['x' => '<b>Ctrl + B</b>']) ?></p>
            <p class="help"><?= lang('Admin.xToToggleFullscreenMode', ['x' => '<b>Ctrl + Alt + Z</b>']) ?></p>
            <?php if ($dataSourcesError): ?>
                <p class="help is-danger"><?= $dataSourcesError ?></p>
            <?php endif; ?>
        </div>

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
    </form>
</div>

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize the Monaco editor with specific options.
        const dataSourcesEditor = window.hyper.factory.monaco({
            editorContainerSelector: "#monaco",
            textareaSelector: "#dataSources",
            language: "json",
            onSave: function(editor) {
                const form = document.getElementById("formDcSettings");
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