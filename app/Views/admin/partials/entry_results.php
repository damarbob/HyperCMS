<?php
/**
 * The part of a model's list that a search, a sort, a filter or a page swaps: the applied filters, the table, the pager
 * and the bar for the selected rows. It is a form, so ticking rows and moving them to the trash posts the ticked ids
 * together with the request that made the list, and the answer is this same part under the same request.
 *
 * @var array<string, mixed> $model
 * @var array<string, mixed> $request
 * @var array<string, mixed> $result
 * @var array<string, mixed> $urls
 * @var array<string, mixed> $can
 */

$trash    = $request['trash'];
$matching = $result['matching'] ?? $result['total'];
$hasQuery = $request['q'] !== '' || $request['filter'] !== [];

// The request is carried by the form, so the list that comes back is the one the person was looking at.
$carry = ['q' => $request['q'], 'sort' => $request['sort'], 'dir' => $request['dir'], 'per' => $request['per'], 'page' => $request['page'], 'trash' => $trash ? 1 : 0];
?>
<div id="entries" class="stack" style="gap: var(--s4)" x-data="selection">
    <?php if ($result['error'] !== '') : ?>
        <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($result['error']) ?></span></div>
    <?php endif ?>
    <?php if ($result['notice'] !== '') : ?>
        <div class="alert warn" role="status"><?= icon('info') ?><span><?= esc($result['notice']) ?></span></div>
    <?php endif ?>

    <div class="applied">
        <?php foreach ($applied as $chip) : ?><?= ui('chip', $chip + ['tone' => 'tint']) ?><?php endforeach ?>
        <?php if ($applied !== []) : ?><a href="<?= attr($urls['list'] . ($trash ? '?trash=1' : '')) ?>"><?= esc(lang('Entries.clearFilters')) ?></a><?php endif ?>
        <span style="margin-left: auto"><?= esc($note) ?></span>
    </div>

    <?php if ($rows === []) : ?>
        <div class="panel">
            <?php if ($result['error'] !== '') : ?>
                <?= ui('emptyState', ['icon' => 'alert', 'title' => lang('Entries.noMatchesTitle'), 'text' => lang('Entries.noMatchesText')]) ?>
            <?php elseif ($trash && $result['total'] === 0) : ?>
                <?= ui('emptyState', ['icon' => 'trash', 'title' => lang('Entries.trashEmptyTitle'), 'text' => lang('Entries.trashEmptyText')]) ?>
            <?php elseif (! $hasQuery && $result['total'] === 0) : ?>
                <?= ui('emptyState', ['title' => lang('Entries.noEntriesTitle'), 'text' => lang('Entries.noEntriesText'), 'actionLabel' => $can['create'] ? lang('Entries.newEntry', ['model' => $model['name']]) : '', 'actionHref' => $links['new']]) ?>
            <?php else : ?>
                <?= ui('emptyState', ['icon' => 'search', 'title' => lang('Entries.noMatchesTitle'), 'text' => lang('Entries.noMatchesText')]) ?>
            <?php endif ?>
        </div>
    <?php else : ?>
        <form method="post" class="stack" style="gap: var(--s4)"
            <?php if ($can['delete']) : ?>action="<?= attr($trash ? $urls['restore'] : $urls['trash']) ?>" hx-post="<?= attr($trash ? $urls['restore'] : $urls['trash']) ?>" hx-target="#entries" hx-swap="outerHTML"<?php endif ?>>
            <?= csrf_field() ?>
            <?php foreach ($carry as $name => $value) : ?><input type="hidden" name="<?= attr($name) ?>" value="<?= attr((string) $value) ?>"><?php endforeach ?>
            <?php foreach ($request['filter'] as $field => $conditions) : foreach ($conditions as $op => $value) : ?>
                <input type="hidden" name="f[<?= attr($field) ?>][<?= attr($op) ?>]" value="<?= attr($value) ?>">
            <?php endforeach; endforeach ?>

            <?= ui('table', [
                'caption'    => (string) $model['name'],
                'selectable' => $can['delete'],
                'columns'    => $columns,
                'rows'       => $rows,
                'attrs'      => $htmx,
            ]) ?>

            <?= ui('pagination', ['page' => $request['page'], 'perPage' => $request['per'], 'total' => (int) $matching, 'href' => $urls['page'], 'attrs' => $htmx]) ?>

            <?php if ($can['delete']) : ?>
                <div class="float-bar" x-show="count > 0" x-cloak role="region" aria-label="<?= attr(lang('Entries.selectedLabel')) ?>">
                    <span class="state"><span class="dot"></span><strong x-text="count"></strong> <?= esc(lang('Entries.selectedLabel')) ?></span>
                    <?php if ($trash) : ?>
                        <button class="btn" type="submit"><?= icon('refresh') ?><span><?= esc(lang('Entries.restore')) ?></span></button>
                    <?php else : ?>
                        <button class="btn danger" type="submit" hx-confirm="<?= attr(lang('Entries.confirmTrash')) ?>"><?= icon('trash') ?><span><?= esc(lang('Entries.moveToTrash')) ?></span></button>
                    <?php endif ?>
                </div>
            <?php endif ?>
        </form>
    <?php endif ?>
</div>
