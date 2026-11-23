<?php
/**
 * The comparison table. Everything on the screen is drawn by the Alpine component `comparison` of `ui/comparison.js`, which
 * reads what it needs from the JSON block at the end, so the page itself is the frame and the two dialogs.
 *
 * @var array<string, mixed> $comparison The models, the data sources, the saved states and the addresses the script calls.
 */

$version = esc((string) config(\Config\Hyper::class)->appVersion, 'url');
$asset   = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= $asset('ui/comparison.css') ?>">
<?php // Formulas are worked out by math.js, which is kept at the version it was tried with. ?>
<script src="https://cdn.jsdelivr.net/npm/mathjs@14.4.0/lib/browser/math.min.js" integrity="sha384-QDoCNeoILx/+zskdiubKaYU0+Adl7RjjOwlZ1txdfum2A98e/Op+wSDVsnZ/vIzL" crossorigin="anonymous" defer></script>
<script src="<?= $asset('ui/comparison-core.js') ?>" defer></script>
<script src="<?= $asset('ui/comparison.js') ?>" defer></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Dc.moduleName')) ?></h1>
        <p><?= esc(lang('Dc.moduleDescription')) ?></p>
    </div>
    <a class="btn quiet" href="<?= attr(base_url('admin/settings/data-comparison')) ?>"><?= icon('settings') ?><span><?= esc(lang('Dc.dataSources')) ?></span></a>
</div>

<div class="comparison" x-data="comparison" x-cloak>
    <div class="comparison-bar">
        <div class="group">
            <select class="select" aria-label="<?= attr(lang('Dc.state')) ?>" @change="chooseState($event.target.value)">
                <option value=""><?= esc(lang('Dc.selectState')) ?></option>
                <template x-for="name in stateNames" :key="name">
                    <option :value="name" :selected="name === stateName" x-text="name"></option>
                </template>
            </select>
            <button type="button" class="btn" :class="{ primary: dirty }" @click="saveState()"><?= icon('check') ?><span><?= esc(lang('Dc.saveState')) ?></span></button>
            <button type="button" class="btn quiet" @click="openName()"><span><?= esc(lang('Dc.saveStateAs')) ?></span></button>
            <button type="button" class="btn quiet" :disabled="!stateName" @click="deleteState()"><?= icon('trash') ?><span><?= esc(lang('Dc.deleteState')) ?></span></button>
            <span class="muted" x-show="dirty" x-cloak><?= esc(lang('Dc.unsavedChanges')) ?></span>
        </div>
        <div class="group">
            <button type="button" class="btn icon-only quiet" :disabled="!canUndo" @click="undo()" aria-label="<?= attr(lang('Dc.undo')) ?>" title="<?= attr(lang('Dc.undo')) ?>"><?= icon('undo') ?></button>
            <button type="button" class="btn icon-only quiet" :disabled="!canRedo" @click="redo()" aria-label="<?= attr(lang('Dc.redo')) ?>" title="<?= attr(lang('Dc.redo')) ?>"><?= icon('redo') ?></button>
            <button type="button" class="btn" @click="openEntry(-1)"><?= icon('plus') ?><span><?= esc(lang('Dc.addEntry')) ?></span></button>
            <button type="button" class="btn" @click="openField(-1)"><?= icon('plus') ?><span><?= esc(lang('Dc.addField')) ?></span></button>
        </div>
    </div>

    <div class="comparison-empty" x-show="!columns.length && !rows.length" x-cloak>
        <?= ui('emptyState', ['icon' => 'table', 'title' => lang('Dc.emptyTitle'), 'text' => lang('Dc.emptyText')]) ?>
    </div>

    <div class="comparison-scroll" x-show="columns.length || rows.length" x-cloak>
        <table class="comparison-table">
            <thead>
                <tr>
                    <th class="corner" scope="col"><span class="visually-hidden"><?= esc(lang('Dc.field')) ?></span></th>
                    <template x-for="(col, ci) in columns" :key="col.id">
                        <th scope="col" draggable="true" :class="{ busy: loading[col.id] }"
                            @dragstart="startDrag('col', ci, $event)" @dragover="over('col', $event)" @drop.prevent="drop('col', ci)" @dragend="endDrag()">
                            <div class="head">
                                <span class="grip" title="<?= attr(lang('Dc.dragToReorderEntry')) ?>"><?= icon('grip') ?></span>
                                <span class="title" x-text="col.label"></span>
                                <span class="actions">
                                    <button type="button" class="btn icon-only quiet small" @click="openEntry(ci)" aria-label="<?= attr(lang('Dc.editEntry')) ?>" title="<?= attr(lang('Dc.editEntry')) ?>"><?= icon('edit') ?></button>
                                    <button type="button" class="btn icon-only quiet small" @click="removeColumn(ci)" aria-label="<?= attr(lang('Dc.removeEntry')) ?>" title="<?= attr(lang('Dc.removeEntry')) ?>"><?= icon('x') ?></button>
                                </span>
                            </div>
                            <div class="sub muted" x-show="sources.length > 1" x-text="(source(col.source) || {}).label"></div>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, ri) in rows" :key="row.fieldId">
                    <tr>
                        <th scope="row" draggable="true"
                            @dragstart="startDrag('row', ri, $event)" @dragover="over('row', $event)" @drop.prevent="drop('row', ri)" @dragend="endDrag()">
                            <div class="head">
                                <span class="grip" title="<?= attr(lang('Dc.dragToReorderField')) ?>"><?= icon('grip') ?></span>
                                <span class="title" x-text="row.title"></span>
                                <span class="actions">
                                    <button type="button" class="btn icon-only quiet small" @click="openField(ri)" aria-label="<?= attr(lang('Dc.editField')) ?>" title="<?= attr(lang('Dc.editField')) ?>"><?= icon('edit') ?></button>
                                    <button type="button" class="btn icon-only quiet small" @click="removeRow(ri)" aria-label="<?= attr(lang('Dc.removeField')) ?>" title="<?= attr(lang('Dc.removeField')) ?>"><?= icon('x') ?></button>
                                </span>
                            </div>
                            <div class="sub">
                                <span class="chip tint" x-show="row.fieldType === 'math-formula'"><?= esc(lang('Dc.mathFormula')) ?></span>
                                <span class="chip" x-show="row.fieldType === 'custom-field'"><?= esc(lang('Dc.customField')) ?></span>
                            </div>
                        </th>
                        <template x-for="col in columns" :key="col.id">
                            <td tabindex="0" :class="{ formula: row.fieldType === 'math-formula', editing: isEditing(row, col) }"
                                @dblclick="startEdit(row, col)" @keydown.enter="isEditing(row, col) || startEdit(row, col)">
                                <template x-if="isEditing(row, col)">
                                    <input type="text" class="cell-input" data-cell-input x-model="editing.text"
                                           @keydown.enter.prevent="commitEdit(row, col)" @keydown.escape.prevent="cancelEdit()" @blur="commitEdit(row, col)">
                                </template>
                                <template x-if="!isEditing(row, col)">
                                    <span class="value" x-text="text(row, col)"></span>
                                </template>
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <?php // The dialog of a column: where the entry comes from, which entry, and the fields to add as rows. ?>
    <dialog class="modal comparison-dialog" id="cmp-entry" aria-labelledby="cmp-entry-title">
        <form method="dialog" @submit.prevent="saveEntry()">
            <div class="head"><h2 id="cmp-entry-title" x-text="entryForm.mode === 'edit' ? <?= attr(json_encode(lang('Dc.editEntry'))) ?> : <?= attr(json_encode(lang('Dc.addEntry'))) ?>"></h2></div>
            <div class="content stack" style="gap: var(--s3)">
                <div class="field" x-show="sources.length > 1">
                    <label for="cmp-source"><?= esc(lang('Dc.dataSource')) ?></label>
                    <select id="cmp-source" class="select" x-model="entryForm.sourceId" @change="loadEntries()">
                        <template x-for="s in sources" :key="s.id"><option :value="String(s.id)" x-text="s.label"></option></template>
                    </select>
                </div>
                <div class="field">
                    <label for="cmp-model"><?= esc(lang('Dc.selectModel')) ?></label>
                    <select id="cmp-model" class="select" x-model="entryForm.modelId" @change="pickModel()">
                        <option value=""><?= esc(lang('Dc.chooseModel')) ?></option>
                        <template x-for="m in models" :key="m.id"><option :value="String(m.id)" x-text="m.name"></option></template>
                    </select>
                </div>
                <div class="field" x-show="entrySource && entrySource.internal">
                    <label for="cmp-entry-pick"><?= esc(lang('Dc.selectEntry')) ?></label>
                    <select id="cmp-entry-pick" class="select" x-model="entryForm.entryId" :disabled="!entryForm.modelId">
                        <option value=""><?= esc(lang('Dc.chooseEntry')) ?></option>
                        <template x-for="e in entryForm.entries" :key="e.id"><option :value="String(e.id)" x-text="e.label"></option></template>
                    </select>
                </div>
                <div class="field" x-show="!(entrySource && entrySource.internal)">
                    <label for="cmp-entry-id"><?= esc(lang('Dc.entryNumber')) ?></label>
                    <input id="cmp-entry-id" class="input" type="text" x-model="entryForm.entryId">
                </div>
                <div class="field">
                    <label for="cmp-label"><?= esc(lang('Dc.entryLabel')) ?></label>
                    <input id="cmp-label" class="input" type="text" x-model="entryForm.label">
                    <p class="help"><?= esc(lang('Dc.optionalLabelForDisplayPurposes')) ?></p>
                </div>
                <fieldset class="field" x-show="entryForm.mode === 'add' && entryForm.pickFields.length" x-cloak>
                    <legend><?= esc(lang('Dc.fieldsToAdd')) ?></legend>
                    <div class="checks">
                        <template x-for="f in entryForm.pickFields" :key="f.id">
                            <label class="check"><input type="checkbox" :value="f.id" x-model="entryForm.fieldIds"><span x-text="f.label"></span></label>
                        </template>
                    </div>
                </fieldset>
                <div x-show="entryForm.preview" x-cloak>
                    <table class="preview">
                        <template x-for="p in (entryForm.preview || [])" :key="p.key"><tr><th x-text="p.key"></th><td x-text="p.value"></td></tr></template>
                    </table>
                </div>
            </div>
            <div class="foot">
                <button type="button" class="btn quiet" :disabled="!entryForm.entryId || entryForm.busy" @click="previewEntry()"><?= icon('eye') ?><span><?= esc(lang('Dc.previewEntry')) ?></span></button>
                <span style="flex: 1"></span>
                <button type="button" class="btn" onclick="this.closest('dialog').close()"><?= esc(lang('Ui.cancel')) ?></button>
                <button type="submit" class="btn primary"><?= esc(lang('Admin.save')) ?></button>
            </div>
        </form>
    </dialog>

    <?php // The dialog of a row: a field of a model, a field of the person's own, or a formula. ?>
    <dialog class="modal comparison-dialog" id="cmp-field" aria-labelledby="cmp-field-title">
        <form method="dialog" @submit.prevent="saveField()">
            <div class="head"><h2 id="cmp-field-title" x-text="fieldForm.mode === 'edit' ? <?= attr(json_encode(lang('Dc.editField'))) ?> : <?= attr(json_encode(lang('Dc.addField'))) ?>"></h2></div>
            <div class="content stack" style="gap: var(--s3)">
                <div class="field">
                    <label for="cmp-field-type"><?= esc(lang('Dc.fieldType')) ?></label>
                    <select id="cmp-field-type" class="select" x-model="fieldForm.type" :disabled="fieldForm.mode === 'edit'">
                        <option value="field"><?= esc(lang('Dc.field')) ?></option>
                        <option value="custom-field"><?= esc(lang('Dc.customField')) ?></option>
                        <option value="math-formula"><?= esc(lang('Dc.mathFormula')) ?></option>
                    </select>
                </div>
                <template x-if="fieldForm.type === 'field'">
                    <div class="stack" style="gap: var(--s3)">
                        <div class="field">
                            <label for="cmp-field-model"><?= esc(lang('Dc.selectModel')) ?></label>
                            <select id="cmp-field-model" class="select" x-model="fieldForm.modelId" @change="fieldForm.fieldId = ''">
                                <option value=""><?= esc(lang('Dc.chooseModel')) ?></option>
                                <template x-for="m in models" :key="m.id"><option :value="String(m.id)" x-text="m.name"></option></template>
                            </select>
                        </div>
                        <div class="field">
                            <label for="cmp-field-pick"><?= esc(lang('Dc.selectField')) ?></label>
                            <select id="cmp-field-pick" class="select" x-model="fieldForm.fieldId" :disabled="!fieldForm.modelId">
                                <option value=""><?= esc(lang('Dc.chooseField')) ?></option>
                                <template x-for="f in (fieldModel ? fieldModel.fields : [])" :key="f.id"><option :value="f.id" x-text="f.label"></option></template>
                            </select>
                        </div>
                    </div>
                </template>
                <template x-if="fieldForm.type === 'custom-field'">
                    <div class="stack" style="gap: var(--s3)">
                        <div class="field">
                            <label for="cmp-custom-label"><?= esc(lang('Dc.customFieldLabel')) ?></label>
                            <input id="cmp-custom-label" class="input" type="text" x-model="fieldForm.label" placeholder="<?= attr(lang('Dc.myCustomField')) ?>">
                        </div>
                        <div class="field">
                            <label for="cmp-custom-id"><?= esc(lang('Dc.customFieldId')) ?></label>
                            <input id="cmp-custom-id" class="input" type="text" x-model="fieldForm.customId" placeholder="<?= attr(lang('Dc.my_custom_field')) ?>">
                        </div>
                    </div>
                </template>
                <template x-if="fieldForm.type === 'math-formula'">
                    <div class="stack" style="gap: var(--s3)">
                        <div class="field">
                            <label for="cmp-formula-label"><?= esc(lang('Dc.formulaLabel')) ?></label>
                            <input id="cmp-formula-label" class="input" type="text" x-model="fieldForm.label">
                        </div>
                        <div class="field">
                            <label for="cmp-formula"><?= esc(lang('Dc.fieldFormula')) ?></label>
                            <input id="cmp-formula" class="input mono" type="text" x-model="fieldForm.formula" placeholder="[price] * [quantity]">
                            <p class="help"><?= esc(lang('Dc.formulaHelp')) ?></p>
                        </div>
                    </div>
                </template>
            </div>
            <div class="foot">
                <button type="button" class="btn" onclick="this.closest('dialog').close()"><?= esc(lang('Ui.cancel')) ?></button>
                <button type="submit" class="btn primary"><?= esc(lang('Admin.save')) ?></button>
            </div>
        </form>
    </dialog>

    <?php // The name of a state, asked when the person saves the layout under a new name. ?>
    <dialog class="modal" id="cmp-name" aria-labelledby="cmp-name-title">
        <form method="dialog" @submit.prevent="saveAs()">
            <div class="head"><h2 id="cmp-name-title"><?= esc(lang('Dc.saveStateAs')) ?></h2></div>
            <div class="content">
                <div class="field">
                    <label for="cmp-state-name"><?= esc(lang('Dc.enterNameForState')) ?></label>
                    <input id="cmp-state-name" class="input" type="text" x-ref="stateName" x-model="nameForm.name" maxlength="200">
                </div>
            </div>
            <div class="foot">
                <button type="button" class="btn" onclick="this.closest('dialog').close()"><?= esc(lang('Ui.cancel')) ?></button>
                <button type="submit" class="btn primary" :disabled="!nameForm.name.trim()"><?= esc(lang('Admin.save')) ?></button>
            </div>
        </form>
    </dialog>
</div>

<script type="application/json" id="comparison-data"><?= json_encode($comparison, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?= $this->endSection() ?>
