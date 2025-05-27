<?php
// Remove the dashboard menu as we are currently in the dashboard
unset($menu[lang('Admin.general')]['dashboard']);
?>
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
<?php if (!empty($menu)) : ?>
    <?php
    $cardBackgroundColors = [
        'hsla(var(--bulma-primary-h),var(--bulma-primary-s),var(--bulma-primary-l), 0.10);',
        'hsla(var(--bulma-link-h),var(--bulma-link-s),var(--bulma-link-l), 0.10);',
        'hsla(var(--bulma-info-h),var(--bulma-info-s),var(--bulma-info-l), 0.10);',
        'hsla(var(--bulma-success-h),var(--bulma-success-s),var(--bulma-success-l), 0.10);',
        'hsla(var(--bulma-warning-h),var(--bulma-warning-s),var(--bulma-warning-l), 0.10);',
        'hsla(var(--bulma-danger-h),var(--bulma-danger-s),var(--bulma-danger-l), 0.10);',
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

    <?php foreach ($menu as $categoryName => $items) : ?>
        <?php $itemList = array_values($items); ?>
        <?php if (!empty($itemList)) : ?>
            <div class="block">
                <h2 class="title is-4"><?= strtoupper(esc($categoryName)) ?></h2>
                <div class="grid is-col-min-9">
                    <?php foreach ($itemList as $index => $item) : ?>
                        <?php $colorIndex = $index % count($cardBackgroundColors); ?>
                        <a href="<?= esc($item['url'], 'attr') ?>">
                            <div class="cell" style="height: 100%;">
                                <div class="card is-flex is-flex-direction-column is-shadowless"
                                    style="--bulma-card-background-color: <?= $cardBackgroundColors[$colorIndex] ?>; 
                                            --bulma-card-footer-border-top: 1px solid var(--bulma-scheme-main); 
                                            height: 100%;">
                                    <div class="card-content is-flex-grow-1">
                                        <h2 class="title <?= $cardTextColors[$colorIndex] ?>">
                                            <span class="text"><?= esc($item['text']) ?></span>
                                            <?php if (!empty($item['tag'])): ?>
                                                <span class="tag is-large has-text-weight-normal <?= $cardTextColors[$colorIndex] ?>" style="background-color: <?= $cardBackgroundColors[$colorIndex] ?>"><?= $item['tag'] ?></span>
                                            <?php endif ?>
                                        </h2>
                                        <?php if (!empty($item['hint'])): ?>
                                            <p class="subtitle <?= $cardTextColors[$colorIndex] ?>"><?= $item['hint'] ?></p>
                                        <?php endif ?>
                                    </div>
                                    <footer class="card-footer">
                                        <p class="title card-footer-item has-text-start">
                                            <span class="icon is-large <?= $cardTextColors[$colorIndex] ?>">
                                                <i class="<?= esc($item['icon']) ?>"></i>
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
    <?php endforeach; ?>
<?php endif; ?>
<?= $this->endSection() ?>