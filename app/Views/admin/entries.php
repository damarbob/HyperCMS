<?php
/**
 * The list of every entry of the models the person may open.
 *
 * @var array<string, mixed>       $request
 * @var array<int, array<string, mixed>> $models
 * @var array<string, mixed>       $links
 */

$hidden = '';
foreach (['sort' => $request['sort'], 'dir' => $request['sort'] !== '' ? $request['dir'] : '', 'per' => $request['per'] !== 25 ? $request['per'] : ''] as $name => $value) {
    if ($value !== '') {
        $hidden .= '<input type="hidden" name="' . attr($name) . '" value="' . attr((string) $value) . '">';
    }
}

$choices = '<option value="">' . esc(lang('Entries.allModels')) . '</option>';
foreach ($models as $id => $model) {
    $choices .= '<option value="' . $id . '"' . ($request['model'] === $id ? ' selected' : '') . '>' . esc((string) $model['name']) . '</option>';
}
$modelFilter = '<label class="visually-hidden" for="model-filter">' . esc(lang('Entries.model')) . '</label><select class="select" id="model-filter" name="model">' . $choices . '</select>';

$creatable = array_filter($models, static fn (array $model): bool => can('entries.create', (int) $model['id']));
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Entries.allTitle')) ?></h1>
        <p><?= esc($total === 1 ? lang('Entries.oneEntry') : lang('Entries.entryCount', ['count' => $total])) ?>. <?= esc(lang('Entries.allText')) ?></p>
    </div>
    <?php if ($creatable !== []) : ?>
        <div class="actions" x-data="menu" @click.outside="close()" @keydown.escape="close()" style="position: relative">
            <button class="btn primary" type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="menu"><?= icon('plus') ?><span><?= esc(lang('Entries.create')) ?></span><?= icon('down') ?></button>
            <div class="menu" role="menu" x-ref="list" x-show="open" x-cloak style="right: 0; top: 38px" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)">
                <?php foreach ($creatable as $id => $model) : ?>
                    <a role="menuitem" href="<?= attr(str_replace('{id}', (string) $id, $links['new'])) ?>"><?= esc((string) $model['name']) ?></a>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
</div>

<?= ui('filterBar', ['action' => $urls['list'], 'searchLabel' => lang('Entries.searchAll'), 'q' => $request['q'], 'target' => '#entries', 'fields' => $hidden . $modelFilter]) ?>

<?= view('admin/partials/all_results', get_defined_vars()) ?>
<?= $this->endSection() ?>
