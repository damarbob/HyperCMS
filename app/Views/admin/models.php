<?php
/**
 * The list of models. The search above it is a form that asks htmx for the list again as the person types, and the list
 * itself is the partial `admin/partials/model_results`.
 *
 * @var array<string, mixed> $request
 * @var array<string, mixed> $urls
 * @var int                  $total
 * @var int|null             $live
 * @var int                  $trashed
 */

$trash  = $request['trash'];
$hidden = '';
foreach (['sort' => $request['sort'], 'dir' => $request['sort'] !== '' ? $request['dir'] : '', 'per' => $request['per'] !== 25 ? $request['per'] : '', 'trash' => $trash ? 1 : ''] as $name => $value) {
    if ($value !== '') {
        $hidden .= '<input type="hidden" name="' . attr($name) . '" value="' . attr((string) $value) . '">';
    }
}
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Models.models')) ?></h1>
        <p><?= esc($total === 1 ? lang('Models.oneModel') : lang('Models.modelCount', ['count' => $total])) ?></p>
    </div>
    <div class="actions">
        <?php if ($trash) : ?>
            <?php if ($trashed > 0) : ?>
                <form method="post" action="<?= attr($urls['purge']) ?>" hx-post="<?= attr($urls['purge']) ?>" hx-target="#models" hx-swap="outerHTML" hx-confirm="<?= attr(lang('Models.confirmEmptyTrash')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="trash" value="1">
                    <?= ui('button', ['label' => lang('Models.emptyTrash'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
                </form>
            <?php endif ?>
        <?php else : ?>
            <?= ui('button', ['label' => lang('Models.newModel'), 'href' => $urls['new'], 'variant' => 'primary', 'icon' => 'plus']) ?>
        <?php endif ?>
    </div>
</div>

<div class="toolbar-row">
    <?= ui('segmented', ['label' => lang('Models.live'), 'items' => [
        ['label' => lang('Models.live'), 'href' => $urls['live'], 'current' => ! $trash, 'count' => $live],
        ['label' => lang('Models.trash'), 'href' => $urls['bin'], 'current' => $trash, 'count' => $trash ? null : ($trashed > 0 ? $trashed : null)],
    ]]) ?>
    <?= ui('filterBar', [
        'action'      => $urls['list'],
        'searchLabel' => lang('Models.searchModels'),
        'q'           => $request['q'],
        'target'      => '#models',
        'fields'      => $hidden,
    ]) ?>
</div>

<?= view('admin/partials/model_results', get_defined_vars()) ?>
<?= $this->endSection() ?>
