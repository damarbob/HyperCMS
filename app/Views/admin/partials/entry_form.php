<?php
/**
 * The whole entry screen below the top bar: the title, the form, the details beside it and the bar that offers to save.
 * A save swaps this part, so the title and the details show the new version and an error shows under its field.
 *
 * @var array<string, mixed>      $model
 * @var array<string, mixed>|null $entry
 * @var string                    $mode        `new` or `edit`
 * @var array<string, mixed>      $values
 * @var array<string, string>     $errors
 * @var list<array<string, mixed>> $definitions
 * @var App\Fields\FieldTypes     $types
 * @var array<string, bool>       $can
 * @var string                    $toolbar     HTML that modules add beside the save button.
 */

$isNew    = $mode === 'new';
$name     = $isNew ? lang('Entries.newTitle', ['model' => $model['name']]) : lang('Entries.editTitle', ['model' => $model['name']]);
$entryId  = (int) ($entry['id'] ?? 0);
$labelKey = $model['label_field'] ?? null;
$subtitle = '';
if (! $isNew) {
    $label    = is_string($labelKey) && is_scalar($values[$labelKey] ?? null) && (string) $values[$labelKey] !== '' ? (string) $values[$labelKey] : '#' . $entryId;
    $subtitle = $label;
}
$stamp = static fn (?string $value): string => $value === null || $value === '' ? '' : $types->get('datetime')->cell($value, ['id' => 'x', 'type' => 'datetime']);
?>
<div id="entry" class="stack" style="gap: var(--s5)">
    <div class="page-head">
        <div>
            <h1><?= esc($name) ?></h1>
            <?php if ($subtitle !== '') : ?><p><?= esc($subtitle) ?><?= ! empty($entry['edited_by']) ? ' · ' . esc(lang('Entries.by', ['name' => (string) $entry['edited_by']])) : '' ?></p><?php endif ?>
        </div>
        <div class="actions">
            <?= $toolbar ?>
            <?= ui('button', ['label' => lang('Entries.history'), 'href' => base_url('admin/entry-data/' . $entryId), 'icon' => 'history']) ?>
            <?php if ($can['update']) : ?><?= ui('button', ['label' => $isNew ? lang('Entries.create') : lang('Entries.save'), 'type' => 'submit', 'variant' => 'primary', 'attrs' => ['form' => 'entry-form']]) ?><?php endif ?>
        </div>
    </div>

    <div class="form-grid">
        <form id="entry-form" method="post" action="<?= attr($formUrl) ?>" novalidate class="fields"
            x-data="{ dirty: <?= $errors !== [] ? 'true' : 'false' ?> }" @input="dirty = true" @change="dirty = true"
            <?php if ($can['update']) : ?>hx-post="<?= attr($formUrl) ?>" hx-target="#entry" hx-swap="outerHTML" hx-disabled-elt="find button[type=submit]"<?php endif ?>>
            <?= csrf_field() ?>

            <?php if ($errors !== [] && $message !== '') : ?>
                <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($message) ?></span></div>
            <?php elseif ($message !== '') : ?>
                <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($message) ?></span></div>
            <?php endif ?>

            <?php if ($definitions === []) : ?>
                <div class="panel"><?= ui('emptyState', ['title' => lang('Entries.noFields'), 'icon' => 'models']) ?></div>
            <?php endif ?>

            <?php foreach ($definitions as $field) : ?>
                <?php $type = $types->get((string) ($field['type'] ?? '')); ?>
                <?php if ($type !== null) : ?><?= $type->input($values[$field['id']] ?? null, $field, $errors[$field['id']] ?? null) ?><?php endif ?>
            <?php endforeach ?>

            <?php if ($can['update'] && $definitions !== []) : ?>
                <div class="float-bar" x-show="dirty" x-cloak role="region" aria-label="<?= attr(lang('Entries.save')) ?>">
                    <span class="state"><span class="dot"></span><?= esc(lang('Entries.unsaved')) ?></span>
                    <?php if ($errors !== []) : ?><span class="muted"><?= esc(lang('Entries.needsAttention', ['count' => count($errors)])) ?></span><?php endif ?>
                    <a class="btn" href="<?= attr($isNew ? $listUrl : base_url('admin/model/' . (int) $model['id'] . '/' . $entryId . '/edit')) ?>"><?= esc(lang('Entries.discard')) ?></a>
                    <button class="btn primary" type="submit"><span><?= esc($isNew ? lang('Entries.create') : lang('Entries.save')) ?></span><span class="spin" aria-hidden="true"></span></button>
                </div>
            <?php endif ?>
        </form>

        <?php if (! $isNew) : ?>
            <aside class="panel quiet details" aria-label="<?= attr(lang('Entries.details')) ?>">
                <header><h2><?= esc(lang('Entries.details')) ?></h2></header>
                <dl class="facts" style="padding: var(--s3) var(--s4)">
                    <dt><?= esc(lang('Entries.entryNumber')) ?></dt><dd class="mono"><?= $entryId ?></dd>
                    <dt><?= esc(lang('Entries.createdOn')) ?></dt><dd><?= $stamp($entry['created_at'] ?? null) ?></dd>
                    <dt><?= esc(lang('Entries.createdBy')) ?></dt><dd><?= esc((string) ($entry['created_by'] ?? '')) ?></dd>
                    <dt><?= esc(lang('Entries.editedOn')) ?></dt><dd><?= $stamp($entry['date_modified'] ?? null) ?></dd>
                    <dt><?= esc(lang('Entries.editedBy')) ?></dt><dd><?= esc((string) ($entry['edited_by'] ?? '')) ?></dd>
                </dl>
                <div class="details-links">
                    <?php if ($can['history']) : ?><a href="<?= attr(base_url('admin/entry-data/' . $entryId)) ?>"><?= esc(lang('Entries.seeHistory')) ?></a><?php endif ?>
                    <?php if ($can['delete']) : ?>
                        <form method="post" action="<?= attr($formUrl . '/trash') ?>" hx-post="<?= attr($formUrl . '/trash') ?>" hx-confirm="<?= attr(lang('Entries.confirmTrash')) ?>">
                            <?= csrf_field() ?>
                            <button class="link-danger" type="submit"><?= esc(lang('Entries.trashThis')) ?></button>
                        </form>
                    <?php endif ?>
                </div>
            </aside>
        <?php endif ?>
    </div>
</div>
