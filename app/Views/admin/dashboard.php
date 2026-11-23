<?php
/**
 * The dashboard: what was edited last, on the left, and the models with their counts and a New button, on the right.
 *
 * @var string                      $username
 * @var list<array<string, mixed>>  $columns The columns of the table of recent entries, without sort links.
 * @var list<array<string, mixed>>  $rows
 * @var list<array<string, mixed>>  $models
 * @var bool                        $canBuild Whether the person may make models.
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
        <section class="panel recent" aria-labelledby="d-recent">
            <header>
                <h2 id="d-recent"><?= esc(lang('Dashboard.recent')) ?></h2>
                <a class="btn quiet small" href="<?= attr(base_url('admin/entries')) ?>"><span><?= esc(lang('Dashboard.allEntries')) ?></span></a>
            </header>
            <?php if ($rows === []) : ?>
                <div class="body"><p class="muted"><?= esc(lang('Dashboard.noRecent')) ?></p></div>
            <?php else : ?>
                <?= ui('table', ['caption' => lang('Dashboard.recent'), 'columns' => $columns, 'rows' => $rows]) ?>
            <?php endif ?>
        </section>

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
    </div>
<?php endif ?>
<?= $this->endSection() ?>
