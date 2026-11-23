<?php
/**
 * The models of the page module. One panel for each model it needs, with the model that is chosen, the ones that fit, and a
 * button that makes one.
 *
 * @var list<array<string, mixed>> $rows
 * @var array<string, string>      $urls
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('PagingSystem.moduleName')) ?></h1>
        <p><?= esc(lang('PagingSystem.settingsText')) ?></p>
    </div>
</div>

<form class="stack" style="gap: var(--s5)" action="<?= attr($urls['save']) ?>" method="post">
    <?= csrf_field() ?>
    <?php foreach ($rows as $row) : ?>
        <section class="panel" aria-labelledby="m-<?= attr($row['key']) ?>">
            <header><h2 id="m-<?= attr($row['key']) ?>"><?= esc($row['label']) ?></h2></header>
            <div class="body stack" style="gap: var(--s3)">
                <p class="muted"><?= esc($row['description']) ?></p>

                <?php if ($row['current'] !== null && ! $row['valid']) : ?>
                    <div class="alert warn" role="status"><?= icon('alert') ?><span><?= esc(lang('PagingSystem.chosenNoLongerFits')) ?></span></div>
                <?php endif ?>

                <?php if ($row['fitting'] === []) : ?>
                    <p><?= esc(lang('PagingSystem.noModelFits')) ?></p>
                    <div>
                        <button class="btn primary" type="submit" formaction="<?= attr($urls['make'] . $row['key']) ?>"><?= esc(lang('PagingSystem.makeModel', ['name' => $row['label']])) ?></button>
                    </div>
                <?php else : ?>
                    <div class="field" style="max-width: 360px">
                        <label for="f-<?= attr($row['key']) ?>"><?= esc(lang('PagingSystem.chosenModel')) ?></label>
                        <select id="f-<?= attr($row['key']) ?>" class="select" name="models[<?= attr($row['key']) ?>]">
                            <?php if (! $row['valid']) : ?><option value=""><?= esc(lang('PagingSystem.noneChosen')) ?></option><?php endif ?>
                            <?php foreach ($row['fitting'] as $model) : ?>
                                <option value="<?= (int) $model['id'] ?>"<?= $row['current'] === $model['id'] ? ' selected' : '' ?>><?= esc($model['name']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div>
                        <button class="btn quiet small" type="submit" formaction="<?= attr($urls['make'] . $row['key']) ?>"><?= esc(lang('PagingSystem.makeAnother', ['name' => $row['label']])) ?></button>
                    </div>
                <?php endif ?>
            </div>
        </section>
    <?php endforeach ?>

    <div><?= ui('button', ['label' => lang('Admin.save'), 'type' => 'submit', 'variant' => 'primary']) ?></div>
</form>
<?= $this->endSection() ?>
