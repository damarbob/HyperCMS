<?php
/**
 * The part of the all-entries list that a search, a sort or a page swaps: the table and the pager.
 *
 * @var array<string, mixed> $request
 * @var list<array<string, mixed>> $rows
 */
?>
<div id="entries" class="stack" style="gap: var(--s4)">
    <?php if ($rows === []) : ?>
        <div class="panel">
            <?php if ($total === 0) : ?>
                <?= ui('emptyState', ['title' => lang('Entries.noEntriesTitle'), 'text' => lang('Entries.noEntriesText')]) ?>
            <?php else : ?>
                <?= ui('emptyState', ['icon' => 'search', 'title' => lang('Entries.noMatchesTitle'), 'text' => lang('Entries.noMatchesText')]) ?>
            <?php endif ?>
        </div>
    <?php else : ?>
        <?= ui('table', ['caption' => lang('Entries.allTitle'), 'columns' => $columns, 'rows' => $rows, 'attrs' => $htmx]) ?>
        <?= ui('pagination', ['page' => $request['page'], 'perPage' => $request['per'], 'total' => $matching, 'href' => $urls['page'], 'attrs' => $htmx]) ?>
    <?php endif ?>
</div>
