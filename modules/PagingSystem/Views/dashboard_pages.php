<?php
/**
 * The panel of the dashboard that lists the pages edited last, each with a way into the editor.
 *
 * @var list<array{id: int, title: string, path: string, edited: string}> $pages
 */
?>
<?php if ($pages === []) : ?>
    <div class="body"><p class="muted"><?= esc(lang('PagingSystem.dashboardNoPages')) ?></p></div>
<?php else : ?>
    <ul class="model-list">
        <?php foreach ($pages as $page) : ?>
            <li>
                <span class="name">
                    <a href="<?= attr(base_url('admin/editor?entry_id=' . (int) $page['id'])) ?>"><?= esc($page['title']) ?></a>
                    <?php if ($page['path'] !== '') : ?><span class="muted"><?= esc('/' . ltrim($page['path'], '/')) ?></span><?php endif ?>
                </span>
                <a class="btn icon-only quiet small" href="<?= attr(base_url('admin/editor?entry_id=' . (int) $page['id'])) ?>" aria-label="<?= attr(lang('PagingSystem.openWithEditor')) ?>" title="<?= attr(lang('PagingSystem.openWithEditor')) ?>"><?= icon('edit') ?></a>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
