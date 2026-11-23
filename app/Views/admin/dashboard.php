<?php
/**
 * The dashboard. A strip of what needs a look, only when something does. Below it the work in a wide column (the entries the
 * person edited last, then what others edited) and a narrow column of the models with their counts and a New button.
 * Modules add panels to either column.
 *
 * @var string                                   $username
 * @var list<array<string, mixed>>               $columns   The columns of the tables of recent entries, without sort links.
 * @var list<array<string, mixed>>               $mine      Entries the person edited last.
 * @var list<array<string, mixed>>               $others    Entries someone else edited last.
 * @var list<array<string, mixed>>               $models
 * @var bool                                     $canBuild  Whether the person may make models.
 * @var list<array<string, mixed>>               $attention
 * @var \App\Modules\Slots\DashboardPanels       $panels
 */

$version = esc((string) config(\Config\Hyper::class)->appVersion, 'url');
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= attr(base_url('assets/ui/dashboard.css') . '?v=' . $version) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Admin.welcomex', ['x' => $username])) ?></h1>
        <p><?= esc(lang('Dashboard.subtitle')) ?></p>
    </div>
</div>

<?php if ($attention !== []) : ?>
    <ul class="attention" aria-label="<?= attr(lang('Dashboard.attention')) ?>">
        <?php foreach ($attention as $item) : ?>
            <li class="<?= attr($item['tone']) ?>">
                <?= icon('alert') ?>
                <span class="text"><?= esc($item['text']) ?></span>
                <span class="links">
                    <?php foreach ($item['links'] as $link) : ?>
                        <a href="<?= attr($link['href']) ?>"><?= esc($link['label']) ?></a>
                    <?php endforeach ?>
                </span>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>

<?php if ($models === []) : ?>
    <?= ui('emptyState', [
        'icon'        => 'models',
        'title'       => lang('Dashboard.noModelsTitle'),
        'text'        => $canBuild ? lang('Dashboard.noModelsBuild') : lang('Dashboard.noModelsAsk'),
        'actionLabel' => $canBuild ? lang('Dashboard.makeModel') : '',
        'actionHref'  => $canBuild ? base_url('admin/models/new') : '',
    ]) ?>
<?php else : ?>
    <div class="dashboard">
        <div class="column">
            <?php if ($mine !== []) : ?>
                <section class="panel recent" aria-labelledby="d-mine">
                    <header>
                        <h2 id="d-mine"><?= esc(lang('Dashboard.mine')) ?></h2>
                    </header>
                    <?= ui('table', ['caption' => lang('Dashboard.mine'), 'columns' => $columns, 'rows' => $mine]) ?>
                </section>
            <?php endif ?>

            <?php if ($others !== [] || $mine === []) : ?>
                <section class="panel recent" aria-labelledby="d-recent">
                    <header>
                        <h2 id="d-recent"><?= esc($mine === [] ? lang('Dashboard.recent') : lang('Dashboard.others')) ?></h2>
                        <a class="btn quiet small" href="<?= attr(base_url('admin/entries')) ?>"><span><?= esc(lang('Dashboard.allEntries')) ?></span></a>
                    </header>
                    <?php if ($others === []) : ?>
                        <div class="body"><p class="muted"><?= esc(lang('Dashboard.noRecent')) ?></p></div>
                    <?php else : ?>
                        <?= ui('table', ['caption' => lang('Dashboard.recent'), 'columns' => $columns, 'rows' => $others]) ?>
                    <?php endif ?>
                </section>
            <?php endif ?>

            <?= $panels->html(\App\Modules\Slots\DashboardPanels::MAIN) ?>
        </div>

        <div class="column">
            <section class="panel models" aria-labelledby="d-models">
                <header><h2 id="d-models"><?= esc(lang('Dashboard.models')) ?></h2></header>
                <ul class="model-list">
                    <?php foreach ($models as $model) : ?>
                        <li>
                            <a class="name" href="<?= attr($model['list']) ?>"><?= esc($model['name']) ?></a>
                            <span class="muted count"><?= (int) $model['count'] ?></span>
                            <?php if ($model['new'] !== null) : ?>
                                <a class="btn icon-only quiet small" href="<?= attr($model['new']) ?>" aria-label="<?= attr(lang('Dashboard.newEntryIn', ['name' => $model['name']])) ?>" title="<?= attr(lang('Dashboard.newEntryIn', ['name' => $model['name']])) ?>"><?= icon('plus') ?></a>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
                <?php if ($canBuild) : ?>
                    <footer class="more"><a class="btn quiet small" href="<?= attr(base_url('admin/models/new')) ?>"><?= icon('plus') ?><span><?= esc(lang('Dashboard.makeModel')) ?></span></a></footer>
                <?php endif ?>
            </section>

            <?= $panels->html(\App\Modules\Slots\DashboardPanels::SIDE) ?>
        </div>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
