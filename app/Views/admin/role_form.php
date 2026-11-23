<?php
/**
 * One role: its details, what it may do anywhere, and what it may do with each model. A person can change only what they
 * have themselves, so a box they may not touch is shown locked.
 *
 * @var array<string, mixed>|null  $role
 * @var array<string, mixed>       $values
 * @var list<array<string, mixed>> $abilities
 * @var list<array<string, mixed>> $models
 * @var list<array<string, mixed>> $others
 * @var string                     $error
 * @var bool                       $locked
 * @var string                     $formUrl
 * @var string                     $deleteUrl
 */

$isNew = $role === null;

// The abilities by where they come from: core first, then each module, then the ones added by hand.
$groups = [];
foreach ($abilities as $ability) {
    $groups[$ability['owner']][] = $ability;
}
uksort($groups, static fn (string $a, string $b): int => [$a === 'core' ? 0 : ($a === 'custom' ? 2 : 1), $a] <=> [$b === 'core' ? 0 : ($b === 'custom' ? 2 : 1), $b]);
$ownerLabel = static fn (string $owner): string => $owner === 'core' ? lang('Roles.ownerCore') : ($owner === 'custom' ? lang('Roles.ownerCustom') : $owner);
$actionLabels = ['read' => lang('Roles.read'), 'create' => lang('Roles.createAction'), 'update' => lang('Roles.update'), 'delete' => lang('Roles.deleteAction'), 'history' => lang('Roles.history')];
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc($isNew ? lang('Roles.newRole') : (string) $role['label']) ?></h1>
        <?php if (! $isNew) : ?><p><code><?= esc($role['name']) ?></code> <?= esc(lang('Roles.peopleHave', ['count' => $role['people']])) ?></p><?php endif ?>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('Roles.back'), 'href' => base_url('admin/roles'), 'icon' => 'chevron-left']) ?>
        <?php if (! $isNew && ! $role['locked'] && $role['people'] === 0) : ?>
            <form method="post" action="<?= attr($deleteUrl) ?>" onsubmit="return confirm(<?= attr(json_encode(lang('Roles.confirmDelete'), JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <?= ui('button', ['label' => lang('Roles.delete'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
            </form>
        <?php endif ?>
    </div>
</div>

<?php if ($error !== '') : ?>
    <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($error) ?></span></div>
<?php endif ?>

<form class="stack role-form" style="gap: var(--s5)" method="post" action="<?= attr($formUrl) ?>" x-data="{ dirty: <?= $isNew || $error !== '' ? 'true' : 'false' ?>, q: '', any(el) { const q = this.q.toLowerCase(); return [...el.querySelectorAll('.ability')].some((a) => a.dataset.text.includes(q)); } }" @input="dirty = true" @change="dirty = true">
    <?= csrf_field() ?>

    <section class="panel" aria-labelledby="role-details">
        <header><h2 id="role-details"><?= esc(lang('Roles.detailsTitle')) ?></h2></header>
        <div class="body details-grid">
            <div class="field">
                <label for="r-label"><?= esc(lang('Roles.label')) ?><span class="req" aria-hidden="true">*</span></label>
                <input id="r-label" class="input" type="text" name="label" value="<?= attr((string) $values['label']) ?>" required autocomplete="off" aria-describedby="r-label-help">
                <p class="help" id="r-label-help"><?= esc(lang('Roles.labelHelp')) ?></p>
            </div>
            <div class="field">
                <label for="r-name"><?= esc(lang('Roles.name')) ?></label>
                <input id="r-name" class="input mono" type="text" name="name" value="<?= attr((string) $values['name']) ?>" <?= $isNew ? 'required' : 'readonly' ?> autocomplete="off" spellcheck="false" aria-describedby="r-name-help">
                <p class="help" id="r-name-help"><?= esc($isNew ? lang('Roles.nameHelp') : lang('Roles.nameFixed')) ?></p>
            </div>
            <div class="field wide">
                <label for="r-description"><?= esc(lang('Roles.description')) ?></label>
                <input id="r-description" class="input" type="text" name="description" value="<?= attr((string) ($values['description'] ?? '')) ?>" autocomplete="off">
            </div>
            <?php if ($isNew) : ?>
                <div class="field wide">
                    <label for="r-copy"><?= esc(lang('Roles.startFrom')) ?></label>
                    <select id="r-copy" class="select" name="copy" aria-describedby="r-copy-help">
                        <option value=""><?= esc(lang('Roles.startFromNone')) ?></option>
                        <?php foreach ($others as $other) : ?><option value="<?= attr($other['name']) ?>"><?= esc($other['label']) ?></option><?php endforeach ?>
                    </select>
                    <p class="help" id="r-copy-help"><?= esc(lang('Roles.startFromHelp')) ?></p>
                </div>
            <?php endif ?>
        </div>
    </section>

    <?php if ($locked) : ?>
        <div class="alert ok" role="status"><?= icon('info') ?><span><?= esc(lang('Roles.lockedText')) ?></span></div>
    <?php else : ?>
        <section class="panel" aria-labelledby="role-abilities">
            <header>
                <div>
                    <h2 id="role-abilities"><?= esc(lang('Roles.abilitiesTitle')) ?></h2>
                    <p class="help" style="margin: 2px 0 0"><?= esc(lang('Roles.abilitiesHelp')) ?></p>
                </div>
                <input class="input" style="max-width: 240px" type="search" x-model="q" placeholder="<?= attr(lang('Roles.search')) ?>" aria-label="<?= attr(lang('Roles.search')) ?>">
            </header>
            <div class="body ability-groups">
                <?php foreach ($groups as $owner => $list) : ?>
                    <fieldset class="ability-group" x-show="! q || any($el)">
                        <legend><?= esc($ownerLabel((string) $owner)) ?></legend>
                        <?php foreach ($list as $ability) : ?>
                            <label class="ability" data-text="<?= attr(mb_strtolower($ability['name'] . ' ' . $ability['label'] . ' ' . $ability['description'])) ?>"
                                x-show="! q || $el.dataset.text.includes(q.toLowerCase())"<?= $ability['mine'] ? '' : ' title="' . attr(lang('Roles.notYours')) . '"' ?>>
                                <input type="checkbox" name="abilities[]" value="<?= attr($ability['name']) ?>"<?= $ability['has'] ? ' checked' : '' ?><?= $ability['mine'] ? '' : ' disabled' ?>>
                                <span>
                                    <strong><?= esc($ability['label']) ?></strong>
                                    <span class="muted"><?= esc($ability['description']) ?></span>
                                    <code><?= esc($ability['name']) ?></code>
                                </span>
                            </label>
                        <?php endforeach ?>
                    </fieldset>
                <?php endforeach ?>
                <p class="muted" x-show="q && ! any($root)" x-cloak><?= esc(lang('Roles.noMatch')) ?></p>
            </div>
        </section>

        <section class="panel" aria-labelledby="role-models">
            <header>
                <div>
                    <h2 id="role-models"><?= esc(lang('Roles.modelsTitle')) ?></h2>
                    <p class="help" style="margin: 2px 0 0"><?= esc(lang('Roles.modelsHelp')) ?></p>
                </div>
            </header>
            <?php if ($models === []) : ?>
                <div class="body"><p class="muted"><?= esc(lang('Roles.noModels')) ?></p></div>
            <?php else : ?>
                <div class="table-wrap">
                    <table class="table access-grid">
                        <caption class="sr-only"><?= esc(lang('Roles.modelsTitle')) ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?= esc(lang('Roles.modelColumn')) ?></th>
                                <?php foreach ($actionLabels as $label) : ?><th scope="col" class="num"><?= esc($label) ?></th><?php endforeach ?>
                                <th scope="col" class="num"><span class="sr-only"><?= esc(lang('Roles.all')) ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($models as $model) : ?>
                                <tr>
                                    <th scope="row"><?= esc($model['name']) ?><?= $model['group'] !== '' ? ' <span class="muted">' . esc($model['group']) . '</span>' : '' ?></th>
                                    <?php foreach ($actionLabels as $action => $label) : ?>
                                        <td class="num">
                                            <input type="checkbox" name="models[<?= (int) $model['id'] ?>][]" value="<?= attr($action) ?>" aria-label="<?= attr($model['name'] . ', ' . $label) ?>"
                                                <?= in_array($action, $model['actions'], true) ? ' checked' : '' ?><?= in_array($action, $model['mine'], true) ? '' : ' disabled' ?>>
                                        </td>
                                    <?php endforeach ?>
                                    <td class="num">
                                        <button type="button" class="btn small quiet" @click="const boxes = $el.closest('tr').querySelectorAll('input:not([disabled])'); const on = [...boxes].some((b) => ! b.checked); boxes.forEach((b) => b.checked = on); dirty = true"><?= esc(lang('Roles.all')) ?></button>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </section>
    <?php endif ?>

    <div class="float-bar" x-show="dirty" x-cloak role="region" aria-label="<?= attr(lang('Roles.save')) ?>">
        <span class="state"><span class="dot"></span><?= esc(lang('Roles.unsaved')) ?></span>
        <a class="btn" href="<?= attr($isNew ? base_url('admin/roles') : base_url('admin/roles/' . $role['name'] . '/edit')) ?>"><?= esc(lang('Roles.discard')) ?></a>
        <button class="btn primary" type="submit"><?= esc($isNew ? lang('Roles.create') : lang('Roles.save')) ?></button>
    </div>
</form>
<?= $this->endSection() ?>
