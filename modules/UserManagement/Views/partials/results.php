<?php
/**
 * The part of the list that a search, a filter, a sort or a page swaps.
 *
 * @var array<string, mixed>       $request
 * @var array<string, string>      $urls
 * @var list<array<string, mixed>> $columns
 * @var list<array<string, mixed>> $rows
 * @var array<string, mixed>       $htmx
 * @var int                        $total
 */
?>
<div id="users" class="stack" style="gap: var(--s4)">
    <?php if ($rows === []) : ?>
        <div class="panel">
            <?php if ($request['q'] !== '' || $request['role'] !== '') : ?>
                <?= ui('emptyState', ['icon' => 'search', 'title' => lang('UserManagement.noMatchesTitle'), 'text' => lang('UserManagement.noMatchesText')]) ?>
            <?php else : ?>
                <?= ui('emptyState', ['icon' => 'users', 'title' => lang('UserManagement.noUsersTitle'), 'text' => lang('UserManagement.noUsersText'), 'actionLabel' => lang('UserManagement.newUser'), 'actionHref' => $urls['new']]) ?>
            <?php endif ?>
        </div>
    <?php else : ?>
        <?= ui('table', ['caption' => lang('UserManagement.moduleName'), 'columns' => $columns, 'rows' => $rows, 'attrs' => $htmx]) ?>
        <?= ui('pagination', ['page' => $request['page'], 'perPage' => $request['per'], 'total' => $total, 'href' => $urls['page'], 'attrs' => $htmx]) ?>
    <?php endif ?>
</div>
