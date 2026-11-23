<?php
/**
 * The versions of one model, newest first. Each version shows what it changed against the one before it, and can be
 * opened to see its fields. An older one can be saved again as a new version.
 *
 * @var array<string, mixed>       $model
 * @var list<array<string, mixed>> $versions
 * @var array<string, string>      $urls
 */
$stamp = static fn (?string $value): string => $value === null || $value === '' ? '' : App\Fields\FieldTypes::core()->get('datetime')->cell($value, ['id' => 'x', 'type' => 'datetime']);
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Models.historyTitle', ['name' => (string) $model['name']])) ?></h1>
        <p><?= esc(lang('Models.historyText')) ?></p>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('Models.back'), 'href' => $urls['edit'], 'icon' => 'chevron-left']) ?>
        <?php if (count($versions) > 1) : ?>
            <form method="post" action="<?= attr($urls['base'] . '/clear') ?>" hx-post="<?= attr($urls['base'] . '/clear') ?>" hx-confirm="<?= attr(lang('Models.confirmClearHistory')) ?>">
                <?= csrf_field() ?>
                <?= ui('button', ['label' => lang('Models.clearHistory'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
            </form>
        <?php endif ?>
    </div>
</div>

<?php if ($versions === []) : ?>
    <div class="panel"><?= ui('emptyState', ['icon' => 'history', 'title' => lang('Models.noHistory'), 'text' => '']) ?></div>
<?php else : ?>
    <ol class="versions">
        <?php foreach ($versions as $version) : ?>
            <li class="panel">
                <details<?= $version['current'] ? ' open' : '' ?>>
                    <summary>
                        <span class="version-title"><?= esc(lang('Models.version')) ?> <?= (int) $version['number'] ?></span>
                        <?php if ($version['current']) : ?><span class="chip ok"><?= esc(lang('Models.current')) ?></span><?php endif ?>
                        <span class="muted"><?= $stamp($version['date']) ?><?= $version['by'] !== null && $version['by'] !== '' ? ' ' . esc(lang('Models.by', ['name' => (string) $version['by']])) : '' ?></span>
                        <span class="muted" style="margin-left: auto"><?= esc(count($version['fields']) === 1 ? lang('Models.oneField') : lang('Models.fieldCount', ['count' => count($version['fields'])])) ?></span>
                    </summary>

                    <?php if ($version['changes'] !== []) : ?>
                        <p class="changes">
                            <?php foreach ($version['changes'] as $change) : ?>
                                <span class="chip <?= $change['kind'] === 'added' ? 'ok' : ($change['kind'] === 'removed' ? 'bad' : 'tint') ?>"><?= esc(lang('Models.' . $change['kind'])) ?> <?= esc($change['field']) ?></span>
                            <?php endforeach ?>
                        </p>
                    <?php endif ?>

                    <dl class="values">
                        <dt><?= esc(lang('Models.name')) ?></dt>
                        <dd><?= esc($version['name']) ?></dd>
                        <?php if ($version['group'] !== '') : ?>
                            <dt><?= esc(lang('Models.group')) ?></dt>
                            <dd><?= esc($version['group']) ?></dd>
                        <?php endif ?>
                        <?php foreach ($version['fields'] as $field) : ?>
                            <dt><?= esc((string) ($field['label'] ?? $field['id'])) ?></dt>
                            <dd><code><?= esc((string) $field['id']) ?></code> <?= esc((string) ($field['type'] ?? '')) ?><?= ! empty($field['filterable']) ? ' ' . esc(lang('Models.filterableTag')) : '' ?></dd>
                        <?php endforeach ?>
                    </dl>

                    <?php if (! $version['current']) : ?>
                        <form method="post" action="<?= attr($urls['base'] . '/versions/' . (int) $version['id'] . '/restore') ?>" hx-post="<?= attr($urls['base'] . '/versions/' . (int) $version['id'] . '/restore') ?>" hx-confirm="<?= attr(lang('Models.confirmRestoreVersion')) ?>" class="version-actions">
                            <?= csrf_field() ?>
                            <?= ui('button', ['label' => lang('Models.restoreVersion'), 'type' => 'submit', 'icon' => 'refresh', 'size' => 'small']) ?>
                        </form>
                    <?php endif ?>
                </details>
            </li>
        <?php endforeach ?>
    </ol>
<?php endif ?>
<?= $this->endSection() ?>
