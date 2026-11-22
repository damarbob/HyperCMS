<?php
/**
 * The versions of one entry, newest first. Each can be opened to see what it held, and an older one can be saved again
 * as a new version.
 *
 * @var array<string, mixed>        $model
 * @var array<string, mixed>        $entry
 * @var list<array<string, mixed>>  $versions
 * @var list<array<string, mixed>>  $definitions
 * @var App\Fields\FieldTypes       $types
 * @var array<string, bool>         $can
 */
$stamp = static fn (?string $value): string => $value === null || $value === '' ? '' : $types->get('datetime')->cell($value, ['id' => 'x', 'type' => 'datetime']);
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Entries.historyTitle', ['name' => $label])) ?></h1>
        <p><?= esc(lang('Entries.historyText')) ?></p>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('Entries.back'), 'href' => $editUrl, 'icon' => 'chevron-left']) ?>
        <?php if ($can['clear'] && count($versions) > 1) : ?>
            <form method="post" action="<?= attr($baseUrl . '/history/clear') ?>" hx-post="<?= attr($baseUrl . '/history/clear') ?>" hx-confirm="<?= attr(lang('Entries.confirmClearHistory')) ?>">
                <?= csrf_field() ?>
                <?= ui('button', ['label' => lang('Entries.clearHistory'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
            </form>
        <?php endif ?>
    </div>
</div>

<ol class="versions">
    <?php foreach ($versions as $version) : ?>
        <li class="panel">
            <details>
                <summary>
                    <span class="version-title"><?= esc(lang('Entries.version')) ?> <?= (int) $version['number'] ?></span>
                    <?php if ($version['current']) : ?><span class="chip ok"><?= esc(lang('Entries.current')) ?></span><?php endif ?>
                    <span class="muted"><?= $stamp($version['date']) ?><?= $version['by'] !== null && $version['by'] !== '' ? ' ' . esc(lang('Entries.by', ['name' => (string) $version['by']])) : '' ?></span>
                </summary>
                <dl class="values">
                    <?php foreach ($definitions as $field) : ?>
                        <?php $type = $types->get((string) ($field['type'] ?? '')); ?>
                        <?php if ($type !== null && ($field['type'] ?? '') !== 'hidden') : ?>
                            <dt><?= esc((string) ($field['label'] ?? $field['id'])) ?></dt>
                            <dd><?= $type->cell($version['values'][$field['id']] ?? null, $field) ?></dd>
                        <?php endif ?>
                    <?php endforeach ?>
                </dl>
                <?php if ($can['restore'] && ! $version['current']) : ?>
                    <form method="post" action="<?= attr($baseUrl . '/versions/' . (int) $version['id'] . '/restore') ?>" hx-post="<?= attr($baseUrl . '/versions/' . (int) $version['id'] . '/restore') ?>" class="version-actions">
                        <?= csrf_field() ?>
                        <?= ui('button', ['label' => lang('Entries.restoreVersion'), 'type' => 'submit', 'icon' => 'refresh', 'size' => 'small']) ?>
                    </form>
                <?php endif ?>
            </details>
        </li>
    <?php endforeach ?>
</ol>
<?= $this->endSection() ?>
