<?php
/**
 * The page of one model's entries. The search and the filters above the list are a form that asks htmx for the list again
 * as they change, and the list itself is the partial `admin/partials/entry_results`.
 *
 * @var array<string, mixed> $model
 * @var array<string, mixed> $request
 * @var array<string, mixed> $result
 * @var array<string, mixed> $urls
 * @var array<string, mixed> $can
 */

$trash  = $request['trash'];
$count  = (int) $result['total'];
$hidden = '';
foreach (['sort' => $request['sort'], 'dir' => $request['sort'] !== '' ? $request['dir'] : '', 'per' => $request['per'] !== 25 ? $request['per'] : '', 'trash' => $trash ? 1 : ''] as $name => $value) {
    if ($value !== '') {
        $hidden .= '<input type="hidden" name="' . attr($name) . '" value="' . attr((string) $value) . '">';
    }
}

$filterPanel = '';
if ($controls !== '') {
    $filterPanel = '<div class="filter-pop" x-data="menu" @click.outside="close()" @keydown.escape="close()">'
        . '<button class="btn" type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="true">' . icon('filter') . '<span>' . esc(lang('Entries.filters')) . '</span>' . ($request['filter'] !== [] ? '<span class="chip tint">' . count($request['filter']) . '</span>' : '') . '</button>'
        . '<div class="menu filter-panel" x-show="open" x-cloak>' . $controls . '</div></div>';
}
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc($model['name']) ?></h1>
        <p><?= esc($count === 1 ? lang('Entries.oneEntry') : lang('Entries.entryCount', ['count' => $count])) ?></p>
    </div>
    <div class="actions">
        <?php if ($can['delete'] && $trash) : ?>
            <?php if ($can['purge']) : ?>
                <form method="post" action="<?= attr($urls['list'] . '/purge') ?>" hx-post="<?= attr($urls['list'] . '/purge') ?>" hx-target="#entries" hx-swap="outerHTML" hx-confirm="<?= attr(lang('Entries.confirmEmptyTrash')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="trash" value="1">
                    <?= ui('button', ['label' => lang('Entries.emptyTrash'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
                </form>
            <?php endif ?>
        <?php else : ?>
            <?= ui('button', ['label' => lang('Entries.history'), 'href' => base_url('admin/model-data/' . (int) $model['id']), 'icon' => 'history']) ?>
            <?= ui('button', ['label' => lang('Entries.exportCsv'), 'href' => $urls['export'], 'icon' => 'download']) ?>
            <?php if ($can['create']) : ?>
                <?= ui('button', ['label' => lang('Entries.newEntry', ['model' => $model['name']]), 'href' => $links['new'], 'variant' => 'primary', 'icon' => 'plus']) ?>
            <?php endif ?>
        <?php endif ?>
    </div>
</div>

<div class="toolbar-row">
    <?= ui('segmented', ['label' => lang('Entries.live'), 'items' => [
        ['label' => lang('Entries.live'), 'href' => $urls['live'], 'current' => ! $trash, 'count' => $trash ? null : $count],
        ['label' => lang('Entries.trash'), 'href' => $urls['bin'], 'current' => $trash],
    ]]) ?>
    <?= ui('filterBar', [
        'action'      => $urls['list'],
        'searchLabel' => lang('Entries.searchIn', ['model' => $model['name']]),
        'q'           => $request['q'],
        'target'      => '#entries',
        'fields'      => $hidden . $filterPanel,
    ]) ?>
</div>

<?= view('admin/partials/entry_results', get_defined_vars()) ?>
<?= $this->endSection() ?>
