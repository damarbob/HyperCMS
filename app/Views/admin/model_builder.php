<?php
/**
 * The model builder: one page to make a model or change it. The fields are edited visually, or as JSON in an editor, and
 * the model's name, group, icon and user groups are on a third tab. The script `ui/builder.js` holds the state and
 * writes the definition into the form when it is sent.
 *
 * @var array<string, mixed>       $model
 * @var int                        $id
 * @var array<string, mixed>       $values
 * @var list<string>               $groups
 * @var array<string, mixed>       $payload
 * @var array<string, mixed>       $errors
 * @var int                        $entries
 * @var string                     $formUrl
 * @var array<string, string>      $urls
 */

$text    = $payload['text'];
$version = esc((string) config(\Config\Hyper::class)->appVersion, 'url');
$general = $errors['general'] ?? [];
$named   = array_diff_key($errors, ['general' => 1, 'fields' => 1]);
$fieldsErrorCount = count($errors['fields'] ?? []);

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc($id > 0 ? (string) $model['name'] : lang('Models.newModel')) ?></h1>
        <?php if ($id > 0) : ?>
            <p>
                <?= esc(lang('Models.builderSummary', ['fields' => count($payload['fields']), 'entries' => $entries])) ?>
                <?php if (! empty($model['date_modified'])) : ?>
                    <?= esc(lang('Models.builderSaved', ['when' => (string) $model['date_modified'], 'name' => (string) ($model['edited_by'] ?? '')])) ?>
                <?php endif ?>
            </p>
        <?php else : ?>
            <p><?= esc(lang('Models.builderNewText')) ?></p>
        <?php endif ?>
    </div>
    <?php if ($id > 0) : ?>
        <div class="actions">
            <?= ui('button', ['label' => lang('Models.builderOpenEntries'), 'href' => $urls['entries'], 'icon' => 'entries']) ?>
            <?= ui('button', ['label' => lang('Models.history'), 'href' => $urls['history'], 'icon' => 'history']) ?>
            <form id="trash-model" method="post" action="<?= attr($urls['trash']) ?>" onsubmit="return confirm(<?= attr(json_encode(lang('Models.confirmTrash'), JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <?= ui('button', ['label' => lang('Models.moveToTrash'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
            </form>
        </div>
    <?php endif ?>
</div>

<?php if ($general !== [] || $named !== [] || $fieldsErrorCount > 0) : ?>
    <div class="alert" role="alert">
        <?= icon('alert') ?>
        <div>
            <strong><?= esc(lang('Models.builderNotSaved')) ?></strong>
            <ul class="problem-list">
                <?php foreach ($general as $message) : ?><li><?= esc($message) ?></li><?php endforeach ?>
                <?php foreach ($named as $messages) : foreach ($messages as $message) : ?><li><?= esc($message) ?></li><?php endforeach; endforeach ?>
                <?php foreach ($errors['fields'] ?? [] as $messages) : foreach ($messages as $message) : ?><li><?= esc($message) ?></li><?php endforeach; endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>

<script type="application/json" id="builder-data"><?= $json ?></script>

<form id="model-form" class="builder" method="post" action="<?= attr($formUrl) ?>" x-data="modelBuilder" x-cloak
    @submit="beforeSubmit($event)" @input="dirty = true" @change="dirty = true" @keydown.window.ctrl.s.prevent="$el.requestSubmit()" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="fields" :value="submitted">
    <input type="hidden" name="label_field" :value="labelField">

    <div class="tabs" role="tablist" aria-label="<?= attr(lang('Models.builderTabs')) ?>">
        <button type="button" role="tab" id="tab-fields" aria-controls="panel-fields" :aria-selected="tab === 'fields'" :tabindex="tab === 'fields' ? 0 : -1" @click="show('fields')" @keydown.arrow-right.prevent="show('json')">
            <span x-text="text.tabFields"></span> <span class="count" x-text="fields.length"></span>
        </button>
        <button type="button" role="tab" id="tab-json" aria-controls="panel-json" :aria-selected="tab === 'json'" :tabindex="tab === 'json' ? 0 : -1" @click="show('json')" @keydown.arrow-left.prevent="show('fields')" @keydown.arrow-right.prevent="show('details')">
            <span x-text="text.tabJson"></span>
        </button>
        <button type="button" role="tab" id="tab-details" aria-controls="panel-details" :aria-selected="tab === 'details'" :tabindex="tab === 'details' ? 0 : -1" @click="show('details')" @keydown.arrow-left.prevent="show('json')">
            <span x-text="text.tabDetails"></span>
        </button>
    </div>

    <!-- Fields -->
    <div id="panel-fields" role="tabpanel" aria-labelledby="tab-fields" x-show="tab === 'fields'" class="builder-fields">
        <section class="field-column" aria-labelledby="fields-heading">
            <div class="column-head">
                <h2 id="fields-heading" x-text="text.tabFields"></h2>
                <div class="menu-anchor" x-data="menu" @click.outside="close()" @keydown.escape="close()">
                    <button class="btn small" type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="true">
                        <?= icon('plus') ?><span x-text="text.addField"></span>
                    </button>
                    <div class="menu type-menu" x-show="open" x-cloak>
                        <template x-for="t in catalog.types" :key="t.name">
                            <button type="button" role="menuitem" @click="addField(t.name); close()" x-text="t.label"></button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="name-by">
                <label for="label-field" x-text="text.nameEntriesBy"></label>
                <select id="label-field" class="select" x-model="labelField">
                    <option value="" x-text="text.nameNone"></option>
                    <template x-for="f in nameable()" :key="f._key">
                        <option :value="f.id" x-text="f.label || f.id" :selected="f.id === labelField"></option>
                    </template>
                </select>
                <p class="help" x-text="text.nameEntriesHelp"></p>
            </div>

            <ul class="field-list" x-show="fields.length > 0" @dragover.prevent @drop.prevent>
                <template x-for="(f, i) in fields" :key="f._key">
                    <li class="field-row" :class="{ selected: f._key === selectedKey, over: dropIndex === i && dragIndex !== i, invalid: hasError(f) }"
                        draggable="true" @dragstart="dragStart(i, $event)" @dragover.prevent="dropIndex = i" @drop.prevent="dropOn(i)" @dragend="dragIndex = null; dropIndex = null">
                        <span class="grip" :title="text.dragHandle.replace('{name}', f.label || f.id)" aria-hidden="true"><?= icon('grip') ?></span>
                        <button type="button" class="pick" @click="select(f)" :aria-current="f._key === selectedKey ? 'true' : null">
                            <span class="row-name">
                                <span x-text="f.label || text.untitled"></span>
                                <span class="chip tint" x-show="f.id === labelField && f.id !== ''" x-text="text.nameChip"></span>
                                <span class="chip bad" x-show="hasError(f)"><?= esc(lang('Models.builderProblem')) ?></span>
                            </span>
                            <span class="row-meta"><code x-text="f.id || '…'"></code> <span x-text="typeLabel(f)"></span><span x-show="f.required">, <?= esc(lang('Models.builderRequiredWord')) ?></span></span>
                        </button>
                        <span class="move">
                            <button type="button" class="icon-btn small" @click="move(i, -1)" :disabled="i === 0" :aria-label="text.moveUp.replace('{name}', f.label || f.id)"><?= icon('up') ?></button>
                            <button type="button" class="icon-btn small" @click="move(i, 1)" :disabled="i === fields.length - 1" :aria-label="text.moveDown.replace('{name}', f.label || f.id)"><?= icon('down') ?></button>
                        </span>
                    </li>
                </template>
            </ul>

            <div class="empty" x-show="fields.length === 0">
                <strong x-text="text.noFields"></strong>
                <span x-text="text.noFieldsText"></span>
            </div>
        </section>

        <section class="editor-column" aria-live="off">
            <template x-if="current">
                <div class="editor" :key="current._key">
                    <header class="editor-head">
                        <h2 x-text="current.label || text.untitled"></h2>
                        <button class="btn small danger" type="button" @click="remove(current)"><?= icon('trash') ?><span x-text="text.removeField"></span></button>
                    </header>

                    <template x-if="errorsOf(current).length > 0">
                        <div class="alert" role="alert"><?= icon('alert') ?><ul class="problem-list"><template x-for="m in errorsOf(current)"><li x-text="m"></li></template></ul></div>
                    </template>

                    <div class="settings-grid">
                        <div class="field">
                            <label for="f-label" x-text="text.fieldLabel"></label>
                            <input id="f-label" class="input" type="text" x-model="current.label" @input="labelTyped(current)" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="f-id" x-text="text.fieldId"></label>
                            <input id="f-id" class="input mono" type="text" x-model="current.id" :readonly="current._saved" @input="current._idTouched = true" autocomplete="off" spellcheck="false" aria-describedby="f-id-help">
                            <p class="help" id="f-id-help" x-text="current._saved ? text.fieldIdLocked : ''" x-show="current._saved"></p>
                        </div>
                        <div class="field">
                            <label for="f-type" x-text="text.fieldType"></label>
                            <select id="f-type" class="select" :value="current.type" @change="changeType(current, $event.target.value)">
                                <template x-for="t in catalog.types" :key="t.name"><option :value="t.name" x-text="t.label" :selected="t.name === current.type"></option></template>
                            </select>
                        </div>

                        <template x-for="s in settingsOf(current)" :key="current._key + s.key">
                            <div class="setting" :class="{ wide: s.control === 'choices' }">
                                <template x-if="s.control === 'select'">
                                    <div class="field"><label :for="'s-' + s.key" x-text="s.label"></label>
                                        <select :id="'s-' + s.key" class="select" x-model="current[s.key]"><template x-for="c in s.choices" :key="c.value"><option :value="c.value" x-text="c.label" :selected="c.value === current[s.key]"></option></template></select></div>
                                </template>
                                <template x-if="s.control === 'number'">
                                    <div class="field"><label :for="'s-' + s.key" x-text="s.label"></label>
                                        <input :id="'s-' + s.key" class="input" type="text" inputmode="decimal" x-model="current[s.key]" :placeholder="text.optional"></div>
                                </template>
                                <template x-if="s.control === 'text'">
                                    <div class="field"><label :for="'s-' + s.key" x-text="s.label"></label>
                                        <input :id="'s-' + s.key" class="input" type="text" x-model="current[s.key]" :placeholder="text.optional" autocomplete="off"></div>
                                </template>
                                <template x-if="s.control === 'model'">
                                    <div class="field"><label :for="'s-' + s.key" x-text="s.label"></label>
                                        <select :id="'s-' + s.key" class="select" x-model="current[s.key]">
                                            <option value="" x-text="catalog.models.length ? text.pickModel : text.noModels"></option>
                                            <template x-for="m in catalog.models" :key="m.id"><option :value="String(m.id)" x-text="m.name" :selected="String(m.id) === String(current[s.key])"></option></template>
                                        </select></div>
                                </template>
                                <template x-if="s.control === 'toggle'">
                                    <label class="check toggle"><input type="checkbox" x-model="current[s.key]" @change="settingChanged(current, s.key)"> <span x-text="s.label"></span></label>
                                </template>
                                <template x-if="s.control === 'choices'">
                                    <fieldset class="choice-editor">
                                        <legend x-text="s.label"></legend>
                                        <template x-for="(c, n) in current[s.key]" :key="n">
                                            <div class="choice-row">
                                                <input class="input" type="text" x-model="c.value" :aria-label="text.choiceValue + ' ' + (n + 1)" :placeholder="text.choiceValue" autocomplete="off" spellcheck="false">
                                                <input class="input" type="text" x-model="c.label" :aria-label="text.choiceLabel + ' ' + (n + 1)" :placeholder="text.choiceLabel" autocomplete="off">
                                                <button type="button" class="icon-btn small" @click="current[s.key].splice(n, 1)" :aria-label="text.removeChoice.replace('{n}', n + 1)"><?= icon('x') ?></button>
                                            </div>
                                        </template>
                                        <button type="button" class="btn small" @click="current[s.key].push({ value: '', label: '' })"><?= icon('plus') ?><span x-text="text.addChoice"></span></button>
                                    </fieldset>
                                </template>
                            </div>
                        </template>

                        <div class="field wide">
                            <label for="f-help-text" x-text="text.fieldHelp"></label>
                            <input id="f-help-text" class="input" type="text" x-model="current.help" autocomplete="off">
                        </div>

                        <template x-if="defaultKind(current) !== ''">
                            <div class="field">
                                <label for="f-default" x-text="text.fieldDefault"></label>
                                <template x-if="defaultKind(current) === 'boolean'"><label class="check"><input id="f-default" type="checkbox" x-model="current.default"> <span x-text="text.defaultChecked"></span></label></template>
                                <template x-if="defaultKind(current) === 'choice'">
                                    <select id="f-default" class="select" x-model="current.default"><option value=""></option><template x-for="c in choicesOf(current)" :key="c.value"><option :value="c.value" x-text="c.label || c.value" :selected="c.value === current.default"></option></template></select>
                                </template>
                                <template x-if="defaultKind(current) === 'color'"><input id="f-default" class="input color" type="color" x-model="current.default"></template>
                                <template x-if="defaultKind(current) === 'text' || defaultKind(current) === 'number'"><input id="f-default" class="input" type="text" x-model="current.default" autocomplete="off"></template>
                            </div>
                        </template>
                    </div>

                    <div class="options">
                        <label class="check"><input type="checkbox" x-model="current.required"> <span x-text="text.required"></span></label>
                        <label class="check"><input type="checkbox" x-model="current.list"> <span x-text="text.showInList"></span></label>
                    </div>

                    <label class="switch" :class="{ off: ! canFilter(current) }">
                        <input type="checkbox" x-model="current.filterable" :disabled="! canFilter(current)">
                        <span>
                            <strong x-text="text.filterable"></strong>
                            <span class="muted" x-text="filterHint(current)"></span>
                            <span class="state-line" x-show="current.filterable && canFilter(current)"><span class="dot" :class="{ on: indexState(current) === 'indexed' }"></span><span x-text="indexLabel(current)"></span></span>
                        </span>
                    </label>

                    <details class="definition">
                        <summary x-text="text.storedDefinition"></summary>
                        <pre class="code" x-text="JSON.stringify(definition(current), null, 2)"></pre>
                    </details>
                </div>
            </template>
            <template x-if="! current">
                <div class="empty"><span x-text="text.selectField"></span></div>
            </template>
        </section>
    </div>

    <!-- JSON -->
    <div id="panel-json" role="tabpanel" aria-labelledby="tab-json" x-show="tab === 'json'" class="builder-json">
        <div class="json-bar">
            <button type="button" class="btn small" @click="checkJson()"><?= icon('check') ?><span x-text="text.jsonCheck"></span></button>
            <span class="muted" x-show="jsonBusy" x-text="text.jsonLoading"></span>
            <span class="state-line" x-show="jsonState === 'ok'"><span class="dot on"></span><span x-text="text.jsonOk"></span></span>
        </div>
        <div class="alert" role="alert" x-show="jsonProblems.length > 0"><?= icon('alert') ?><ul class="problem-list"><template x-for="m in jsonProblems"><li x-text="m"></li></template></ul></div>
        <div class="monaco-box" x-ref="monaco" x-show="! jsonFallback" role="group" :aria-label="text.tabJson"></div>
        <textarea class="textarea code-area" x-show="jsonFallback" x-model="json" rows="24" spellcheck="false" :aria-label="text.tabJson"></textarea>
        <p class="help" x-show="jsonFallback" x-text="text.jsonFailed"></p>
    </div>

    <!-- Details -->
    <div id="panel-details" role="tabpanel" aria-labelledby="tab-details" x-show="tab === 'details'" class="builder-details">
        <div class="details-grid">
            <div class="field <?= isset($errors['name']) ? 'invalid' : '' ?>">
                <label for="m-name"><?= esc(lang('Models.name')) ?><span class="req" aria-hidden="true">*</span></label>
                <input id="m-name" class="input" type="text" name="name" value="<?= attr($values['name']) ?>" required autocomplete="off" aria-describedby="m-name-help">
                <p class="help" id="m-name-help"><?= esc(lang('Models.detailsNameHelp')) ?></p>
                <?php foreach ($errors['name'] ?? [] as $message) : ?><p class="error"><?= icon('alert') ?><?= esc($message) ?></p><?php endforeach ?>
            </div>
            <div class="field <?= isset($errors['group']) ? 'invalid' : '' ?>">
                <label for="m-group"><?= esc(lang('Models.group')) ?></label>
                <input id="m-group" class="input" type="text" name="group" value="<?= attr($values['group']) ?>" autocomplete="off" aria-describedby="m-group-help">
                <p class="help" id="m-group-help"><?= esc(lang('Models.detailsGroupHelp')) ?></p>
                <?php foreach ($errors['group'] ?? [] as $message) : ?><p class="error"><?= icon('alert') ?><?= esc($message) ?></p><?php endforeach ?>
            </div>

            <fieldset class="field wide icon-pick" x-data="{ icon: <?= attr(json_encode($values['icon'], JSON_UNESCAPED_UNICODE)) ?> }">
                <legend><?= esc(lang('Models.detailsIcon')) ?></legend>
                <input type="hidden" name="icon" :value="icon">
                <div class="icon-grid">
                    <?php foreach ($payload['icons'] as $name) : ?>
                        <label class="icon-choice" title="<?= attr($name) ?>">
                            <input type="radio" name="icon-choice" value="<?= attr($name) ?>" :checked="icon === '<?= attr($name) ?>'" @change="icon = '<?= attr($name) ?>'">
                            <span><?= icon($name) ?></span>
                            <span class="sr-only"><?= esc($name) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
                <p class="help" x-show="icon !== '' && ! <?= attr(json_encode($payload['icons'])) ?>.includes(icon)"><?= esc(lang('Models.detailsIconKept')) ?> <code x-text="icon"></code></p>
            </fieldset>

            <fieldset class="field wide">
                <legend><?= esc(lang('Models.detailsUserGroups')) ?><span class="req" aria-hidden="true">*</span></legend>
                <div class="options">
                    <?php foreach ($groups as $group) : ?>
                        <label class="check"><input type="checkbox" name="user_groups[]" value="<?= attr($group) ?>"<?= in_array($group, $values['user_groups'], true) ? ' checked' : '' ?>> <?= esc(ucfirst($group)) ?></label>
                    <?php endforeach ?>
                </div>
                <p class="help"><?= esc(lang('Models.detailsUserGroupsHelp')) ?></p>
                <?php foreach ($errors['user_groups'] ?? [] as $message) : ?><p class="error"><?= icon('alert') ?><?= esc($message) ?></p><?php endforeach ?>
            </fieldset>
        </div>
    </div>

    <p class="sr-only" role="status" aria-live="polite" x-text="announce"></p>

    <div class="float-bar" x-show="dirty" x-cloak role="region" aria-label="<?= attr(lang('Models.builderSaveBar')) ?>">
        <span class="state"><span class="dot"></span><span x-text="text.unsaved"></span></span>
        <span class="muted" x-show="newIndexes() > 0" x-text="indexNote()"></span>
        <a class="btn" href="" @click.prevent="location.reload()"><?= esc(lang('Models.builderDiscard')) ?></a>
        <button class="btn primary" type="submit"><?= esc($id > 0 ? lang('Models.builderSave') : lang('Models.builderCreate')) ?></button>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= attr(base_url('assets/ui/builder.js') . '?v=' . $version) ?>" defer></script>
<?= $this->endSection() ?>
