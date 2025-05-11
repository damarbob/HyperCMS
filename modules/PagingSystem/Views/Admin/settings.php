<?php
helper('form');

$primaryModelError = validation_show_error('paging_system_primary_model_id');
$assetsModelError = validation_show_error('paging_system_assets_model_id');
$metaModelError = validation_show_error('paging_system_meta_model_id');
?>
<?= $this->extend('admin/layout/page') ?>
<?= $this->section('content') ?>
<div class="block">
    <h1 class="title">
        <?= lang('PagingSystem.moduleName') ?>
    </h1>
    <p class="subtitle">
        <?= lang('PagingSystem.moduleDescription') ?>
    </p>
    <form action="<?= base_url('admin/settings/paging-system/update') ?>" method="POST">
        <?= csrf_field() ?>

        <!-- Primary model selection -->
        <div class="field">
            <label class="label"><?= lang('PagingSystem.primary') ?></label>
            <div class="control">
                <div class="select">
                    <select name="paging_system_primary_model_id">
                        <?php for ($i = 0; $i < count($pagingSystemEligibleModelNames); $i++): ?>
                            <option value="<?= $pagingSystemEligibleModelIds[$i] ?>"><?= $pagingSystemEligibleModelNames[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <p class="help"><?= lang('PagingSystem.chosenPrimaryModelWillBeRoutedToTheFrontend') ?></p>
            <?php if ($primaryModelError): ?>
                <p class="help is-danger"><?= $primaryModelError ?></p>
            <?php endif; ?>
        </div>

        <!-- Assets model selection -->
        <div class="field">
            <label class="label"><?= lang('PagingSystem.assets') ?></label>
            <div class="control">
                <div class="select">
                    <select name="paging_system_assets_model_id">
                        <?php for ($i = 0; $i < count($pagingSystemAssetsEligibleModelNames); $i++): ?>
                            <option value="<?= $pagingSystemAssetsEligibleModelIds[$i] ?>"><?= $pagingSystemAssetsEligibleModelNames[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <p class="help"><?= lang('PagingSystem.selectedModelWillServeAsPrimaryModelForServingAssets') ?></p>
            <?php if ($metaModelError): ?>
                <p class="help is-danger"><?= $metaModelError ?></p>
            <?php endif; ?>
        </div>

        <!-- Meta model selection -->
        <div class="field">
            <label class="label"><?= lang('PagingSystem.meta') ?></label>
            <div class="control">
                <div class="select">
                    <select name="paging_system_meta_model_id">
                        <?php for ($i = 0; $i < count($pagingSystemMetaEligibleModelNames); $i++): ?>
                            <option value="<?= $pagingSystemMetaEligibleModelIds[$i] ?>"><?= $pagingSystemMetaEligibleModelNames[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <p class="help"><?= lang('PagingSystem.selectedMetaModelWillBeUsedToInject') ?></p>
            <?php if ($metaModelError): ?>
                <p class="help is-danger"><?= $metaModelError ?></p>
            <?php endif; ?>
        </div>

        <!-- Submit button -->
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
        </div>

    </form>
</div>
<?= $this->endSection() ?>