<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1>Design system</h1>
        <p>Every part a screen is built from. This page exists outside production only.</p>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => 'Show a toast', 'icon' => 'info', 'attrs' => ['@click' => "\$dispatch('hyper:toast', { type: 'ok', message: 'Saved the model.' })"]]) ?>
        <?= ui('button', ['label' => 'Show an error', 'variant' => 'danger', 'attrs' => ['@click' => "\$dispatch('hyper:toast', { type: 'bad', message: 'The entry could not be saved.' })"]]) ?>
        <?= ui('button', ['label' => 'New entry', 'variant' => 'primary', 'icon' => 'plus', 'href' => '#']) ?>
    </div>
</div>

<?= ui('tabs', ['label' => 'Sections', 'items' => [['label' => 'Parts', 'href' => '#parts', 'current' => true], ['label' => 'Forms', 'href' => '#forms'], ['label' => 'Lists', 'href' => '#lists']]]) ?>

<section class="stack" id="parts" aria-labelledby="parts-title">
    <h2 id="parts-title">Buttons and chips</h2>
    <div class="row">
        <?= ui('button', ['label' => 'Default']) ?>
        <?= ui('button', ['label' => 'Primary', 'variant' => 'primary']) ?>
        <?= ui('button', ['label' => 'Danger', 'variant' => 'danger']) ?>
        <?= ui('button', ['label' => 'Delete', 'variant' => 'solid-danger', 'icon' => 'trash']) ?>
        <?= ui('button', ['label' => 'Quiet', 'variant' => 'quiet']) ?>
        <?= ui('button', ['label' => 'Small', 'size' => 'small']) ?>
        <?= ui('button', ['label' => 'Edit', 'icon' => 'edit', 'iconOnly' => true]) ?>
        <?= ui('button', ['label' => 'Disabled', 'disabled' => true]) ?>
    </div>
    <div class="row">
        <?= ui('chip', ['label' => 'News']) ?>
        <?= ui('chip', ['label' => 'Saved', 'tone' => 'ok']) ?>
        <?= ui('chip', ['label' => 'Indexing', 'tone' => 'warn']) ?>
        <?= ui('chip', ['label' => 'Failed', 'tone' => 'bad']) ?>
        <?= ui('chip', ['label' => 'Tags is News', 'tone' => 'tint', 'removeHref' => '#', 'removeLabel' => 'Remove the Tags filter']) ?>
    </div>
    <div class="row">
        <?= ui('segmented', ['label' => 'Show', 'items' => [['label' => 'Live', 'href' => '#', 'current' => true, 'count' => 23], ['label' => 'Trash', 'href' => '#', 'count' => 2]]]) ?>
        <button class="btn" type="button" @click="document.getElementById('demo-dialog').showModal()">Open a dialog</button>
    </div>
    <div class="alert warn" role="status"><?= icon('info') ?><span>An alert says what happened and what to do next.</span></div>
</section>

<section class="stack" id="forms" aria-labelledby="forms-title" style="max-width: 560px">
    <h2 id="forms-title">Fields</h2>
    <?= ui('field', ['id' => 'f-title', 'label' => 'Title', 'value' => 'Getting started', 'required' => true]) ?>
    <?= ui('field', ['id' => 'f-views', 'label' => 'Views', 'value' => 'many', 'error' => 'Views must be a number, but "many" is not one.']) ?>
    <?= ui('field', ['id' => 'f-kind', 'label' => 'Kind', 'kind' => 'select', 'value' => 'b', 'options' => ['a' => 'Article', 'b' => 'Page'], 'help' => 'Pages have a path.']) ?>
    <?= ui('field', ['id' => 'f-about', 'label' => 'About', 'kind' => 'textarea', 'placeholder' => 'A few lines']) ?>
    <label class="switch"><input type="checkbox" checked><span><strong>Filterable</strong><span class="muted">Lists can sort on this field quickly.</span></span></label>
</section>

<section class="stack" id="lists" aria-labelledby="lists-title" x-data="selection">
    <h2 id="lists-title">A list</h2>
    <?= ui('filterBar', ['action' => '#', 'searchLabel' => 'Search articles', 'applied' => [['label' => 'Tags is News', 'removeHref' => '#', 'removeLabel' => 'Remove the Tags filter']], 'note' => 'Sorted by Published, newest first']) ?>
    <?= ui('table', [
        'caption'    => 'Articles',
        'selectable' => true,
        'columns'    => [
            ['key' => 'title', 'label' => 'Title', 'class' => 'label-cell', 'href' => '#', 'dir' => null],
            ['key' => 'views', 'label' => 'Views', 'class' => 'num', 'href' => '#', 'dir' => null],
            ['key' => 'published', 'label' => 'Published', 'href' => '#', 'dir' => 'desc'],
            ['key' => 'tags', 'label' => 'Tags'],
        ],
        'rows'       => [
            ['id' => 1, 'label' => 'Getting started with HyperCMS', 'cells' => ['title' => '<a href="#">Getting started with HyperCMS</a>', 'views' => '10', 'published' => '2 Feb 2026, 14:05', 'tags' => ui('chip', ['label' => 'News'])], 'actions' => ui('button', ['label' => 'Edit', 'variant' => 'quiet', 'size' => 'small', 'href' => '#'])],
            ['id' => 2, 'label' => 'Apple pie recipe', 'cells' => ['title' => '<a href="#">Apple pie recipe</a>', 'views' => '<span class="muted">–</span>', 'published' => '4 Feb 2026, 14:05', 'tags' => ui('chip', ['label' => 'News']) . ui('chip', ['label' => 'Tech'])], 'actions' => ui('button', ['label' => 'Edit', 'variant' => 'quiet', 'size' => 'small', 'href' => '#'])],
        ],
    ]) ?>
    <?= ui('pagination', ['page' => 3, 'perPage' => 10, 'total' => 120, 'href' => '#page-{page}']) ?>
    <div class="panel"><?= ui('emptyState', ['title' => 'No articles yet', 'text' => 'Write the first one, or import entries from a file.', 'actionLabel' => 'New article', 'actionHref' => '#']) ?></div>

    <div class="float-bar" x-show="count > 0" x-cloak role="region" aria-label="Selected entries">
        <span class="state"><span class="dot"></span><span x-text="count + ' selected'"></span></span>
        <button class="btn" type="button">Export CSV</button>
        <button class="btn danger" type="button">Move to trash</button>
    </div>
</section>

<?= ui('modal', ['id' => 'demo-dialog', 'title' => 'Move 2 entries to the trash?', 'body' => '<p>You can restore them from the trash until it is emptied.</p>', 'confirmLabel' => 'Move to trash', 'danger' => true]) ?>
<?= $this->endSection() ?>
