<?php
/**
 * The part of the list of models that a search, a sort or a page swaps: the table, the pager and the bar for the
 * selected rows. It is a form, so ticking rows and moving them to the trash posts the ticked ids together with the
 * request that made the list, and the answer is this same part under the same request.
 *
 * @var array<string, mixed>       $request
 * @var array<string, mixed>       $urls
 * @var list<array<string, mixed>> $columns
 * @var list<array<string, mixed>> $rows
 * @var array<string, mixed>       $htmx
 * @var int                        $total
 */

$trash = $request['trash'];
$carry = ['q' => $request['q'], 'sort' => $request['sort'], 'dir' => $request['dir'], 'per' => $request['per'], 'page' => $request['page'], 'trash' => $trash ? 1 : 0];
?>
<div id="models" class="stack" style="gap: var(--s4)" x-data="selection">
    <?php if ($rows === []) : ?>
        <div class="panel">
            <?php if ($request['q'] !== '') : ?>
                <?= ui('emptyState', ['icon' => 'search', 'title' => lang('Models.noMatchesTitle'), 'text' => lang('Models.noMatchesText')]) ?>
            <?php elseif ($trash) : ?>
                <?= ui('emptyState', ['icon' => 'trash', 'title' => lang('Models.trashEmptyTitle'), 'text' => lang('Models.trashEmptyText')]) ?>
            <?php else : ?>
                <?= ui('emptyState', ['title' => lang('Models.noModelsTitle'), 'text' => lang('Models.noModelsText'), 'actionLabel' => lang('Models.newModel'), 'actionHref' => $urls['new']]) ?>
            <?php endif ?>
        </div>
    <?php else : ?>
        <form method="post" class="stack" style="gap: var(--s4)" action="<?= attr($trash ? $urls['restore'] : $urls['trash']) ?>" hx-post="<?= attr($trash ? $urls['restore'] : $urls['trash']) ?>" hx-target="#models" hx-swap="outerHTML">
            <?= csrf_field() ?>
            <?php foreach ($carry as $name => $value) : ?><input type="hidden" name="<?= attr($name) ?>" value="<?= attr((string) $value) ?>"><?php endforeach ?>

            <?= ui('table', [
                'caption'    => lang('Models.models'),
                'selectable' => true,
                'columns'    => $columns,
                'rows'       => $rows,
                'attrs'      => $htmx,
            ]) ?>

            <?= ui('pagination', ['page' => $request['page'], 'perPage' => $request['per'], 'total' => $total, 'href' => $urls['page'], 'attrs' => $htmx]) ?>

            <div class="float-bar" x-show="count > 0" x-cloak role="region" aria-label="<?= attr(lang('Models.selectedLabel')) ?>">
                <span class="state"><span class="dot"></span><strong x-text="count"></strong> <?= esc(lang('Models.selectedLabel')) ?></span>
                <?php if ($trash) : ?>
                    <button class="btn" type="submit"><?= icon('refresh') ?><span><?= esc(lang('Models.restore')) ?></span></button>
                <?php else : ?>
                    <button class="btn danger" type="submit" hx-confirm="<?= attr(lang('Models.confirmTrash')) ?>"><?= icon('trash') ?><span><?= esc(lang('Models.moveToTrash')) ?></span></button>
                <?php endif ?>
            </div>
        </form>
    <?php endif ?>
</div>
