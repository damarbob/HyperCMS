<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <h1 class="title">
        <?= lang('Admin.welcomex', ['x' => auth()->user()->username]) ?>
    </h1>
    <p class="subtitle">
        <?= $quote ?? lang('Admin.startSomethingBig') ?>
    </p>
</div>
<?php if ($models) : ?>
    <div class="block">
        <div class="grid is-col-min-9">
            <?php
            $cardBackgroundColors = [
                'hsla(var(--bulma-primary-h),var(--bulma-primary-s),var(--bulma-primary-l), 0.15);',
                'hsla(var(--bulma-link-h),var(--bulma-link-s),var(--bulma-link-l), 0.15);',
                'hsla(var(--bulma-info-h),var(--bulma-info-s),var(--bulma-info-l), 0.15);',
                'hsla(var(--bulma-success-h),var(--bulma-success-s),var(--bulma-success-l), 0.15);',
                'hsla(var(--bulma-warning-h),var(--bulma-warning-s),var(--bulma-warning-l), 0.15);',
                'hsla(var(--bulma-danger-h),var(--bulma-danger-s),var(--bulma-danger-l), 0.15);',
            ];
            $cardTextColors = [
                'has-text-primary',
                'has-text-link',
                'has-text-info',
                'has-text-success',
                'has-text-warning',
                'has-text-danger'
            ];
            ?>
            <?php foreach ($models as $index => $model) : ?>
                <a href="<?= base_url('admin/model?id=' . $model['id']) ?>">
                    <div class="cell" style="height: 100%;">
                        <!-- Apply a different background color for each model -->
                        <div class="card is-flex is-flex-direction-column is-shadowless" style="--bulma-card-background-color: <?= $cardBackgroundColors[$index % count($cardBackgroundColors)] ?>; --bulma-card-footer-border-top: 1px solid var(--bulma-scheme-main); height: 100%;">
                            <div class="card-content is-flex-grow-1">
                                <h2 class="title <?= $cardTextColors[$index % count($cardTextColors)] ?>">
                                    <span class="text"><?= $model['name'] ?></span>
                                </h2>
                                <p class="subtitle"><?= lang('Admin.managexEntries', ['x' => strtolower($model['name'])]) ?></p>
                            </div>
                            <footer class="card-footer">
                                <p class="title card-footer-item has-text-start">
                                    <span class="icon is-large <?= $cardTextColors[$index % count($cardTextColors)] ?>">
                                        <i class="<?= !empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open' ?>"></i>
                                    </span>
                                </p>
                            </footer>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>

        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>