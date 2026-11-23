<?php
/**
 * The library as the screen and the picker draw it: where the person is, the folders and files in it, and the actions on
 * them. It is a form, so a ticked file is posted with the state of the view and the answer is this same part afterwards.
 *
 * @var string                      $mode       `library` or `picker`
 * @var array<string, mixed>        $state      folder, q, kind, sort, page, accept, multiple
 * @var int                         $per
 * @var int                         $total
 * @var list<array<string, mixed>>  $files
 * @var list<array<string, mixed>>  $folders
 * @var list<array<string, mixed>>  $trail
 * @var callable                    $link
 * @var list<string>                $allFolders
 * @var string                      $base
 */

$picker = $mode === 'picker';
$id     = $picker ? 'picker-browser' : 'library';
$swap   = ['hx-target' => '#' . $id, 'hx-swap' => 'outerHTML', 'hx-push-url' => $picker ? 'false' : 'true'];
$url    = static fn (string $path): string => base_url('admin/media/' . $path);
$accept = ['image' => 'image/*', 'video' => 'video/*', 'audio' => 'audio/*'][$state['accept']] ?? '';

$carry = ['mode' => $mode, 'folder' => $state['folder'], 'q' => $state['q'], 'kind' => $state['kind'], 'sort' => $state['sort'], 'page' => $state['page'], 'accept' => $state['accept'], 'multiple' => $state['multiple'] ? '1' : '0'];

$pageHref = str_replace('987654321', '{page}', $link(['page' => 987654321]));
$searching = $state['q'] !== '';
$empty     = $files === [] && $folders === [];
?>
<div id="<?= attr($id) ?>" class="media <?= $picker ? 'is-picker' : '' ?>"
    x-data="mediaBrowser(<?= attr(json_encode([
        'picker'   => $picker,
        'multiple' => $state['multiple'],
        'refresh'  => $link(),
        'upload'   => $url('upload'),
        'folder'   => $state['folder'],
        'texts'    => [
            'selected'   => lang('Media.selectedCount'),
            'uploading'  => lang('Media.uploading'),
            'uploaded'   => lang('Media.uploaded'),
            'uploadedOne' => lang('Media.uploadedOne'),
            'linkCopied' => lang('Media.linkCopied'),
            'failed'     => lang('Media.uploadFailed'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>)"
    @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="dragging = false; upload($event.dataTransfer.files)"
    :class="{ dragging: dragging }">

    <nav class="media-crumbs" aria-label="<?= attr(lang('Media.location')) ?>">
        <ol>
            <?php foreach ($trail as $at => $crumb) : ?>
                <li>
                    <?php if ($at === array_key_last($trail)) : ?>
                        <span aria-current="page"><?= esc($crumb['label']) ?></span>
                    <?php else : ?>
                        <a href="<?= attr($crumb['url']) ?>" hx-get="<?= attr($crumb['url']) ?>" <?= attrs($swap) ?>><?= esc($crumb['label']) ?></a>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ol>
    </nav>

    <form class="toolbar" method="get" action="<?= attr($base) ?>" role="search" hx-get="<?= attr($base) ?>" <?= attrs($swap) ?> hx-trigger="input changed delay:300ms from:find input[type=search], change">
        <input type="hidden" name="folder" value="<?= attr($state['folder']) ?>">
        <?php if ($state['accept'] !== 'any') : ?><input type="hidden" name="accept" value="<?= attr($state['accept']) ?>"><?php endif ?>
        <?php if ($state['multiple']) : ?><input type="hidden" name="multiple" value="1"><?php endif ?>
        <input class="input grow" type="search" name="q" value="<?= attr($state['q']) ?>" placeholder="<?= attr(lang('Media.search')) ?>" aria-label="<?= attr(lang('Media.search')) ?>">
        <?php if ($state['accept'] === 'any') : ?>
            <select class="select" name="kind" aria-label="<?= attr(lang('Media.kind')) ?>">
                <?php foreach (['any' => 'anyKind', 'image' => 'kindImage', 'video' => 'kindVideo', 'audio' => 'kindAudio', 'document' => 'kindDocument'] as $value => $key) : ?>
                    <option value="<?= attr($value) ?>"<?= $state['kind'] === $value ? ' selected' : '' ?>><?= esc(lang('Media.' . $key)) ?></option>
                <?php endforeach ?>
            </select>
        <?php endif ?>
        <select class="select" name="sort" aria-label="<?= attr(lang('Media.sort')) ?>">
            <option value="new"<?= $state['sort'] === 'new' ? ' selected' : '' ?>><?= esc(lang('Media.sortNew')) ?></option>
            <option value="name"<?= $state['sort'] === 'name' ? ' selected' : '' ?>><?= esc(lang('Media.sortName')) ?></option>
        </select>
        <noscript><button class="btn" type="submit"><?= esc(lang('Ui.search')) ?></button></noscript>
    </form>

    <form method="post" action="<?= attr($base) ?>" class="stack media-form" style="gap: var(--s4)" @submit.prevent <?= attrs(['hx-target' => '#' . $id, 'hx-swap' => 'outerHTML', 'hx-push-url' => 'false']) ?> @change="update($event)">
        <?= csrf_field() ?>
        <?php foreach ($carry as $name => $value) : ?><input type="hidden" name="<?= attr($name) ?>" value="<?= attr((string) $value) ?>"><?php endforeach ?>

        <div class="media-actions">
            <input type="file" multiple hidden x-ref="file"<?= $accept !== '' ? ' accept="' . attr($accept) . '"' : '' ?> @change="upload($event.target.files); $event.target.value = ''">
            <?= ui('button', ['label' => lang('Media.upload'), 'icon' => 'upload', 'variant' => 'primary', 'attrs' => ['type' => 'button', '@click' => '$refs.file.click()']]) ?>
            <?= ui('button', ['label' => lang('Media.newFolder'), 'icon' => 'folder', 'attrs' => ['type' => 'button', '@click' => 'ask(\'newFolder\')']]) ?>
            <?php if (! $picker && $state['folder'] !== '') : ?>
                <?= ui('button', ['label' => lang('Media.renameFolder'), 'icon' => 'edit', 'variant' => 'quiet', 'attrs' => ['type' => 'button', '@click' => 'ask(\'renameFolder\')']]) ?>
                <?= ui('button', ['label' => lang('Media.deleteFolder'), 'icon' => 'trash', 'variant' => 'quiet', 'attrs' => ['type' => 'button', 'hx-post' => $url('folders/delete'), 'hx-confirm' => lang('Media.confirmDeleteFolder')]]) ?>
            <?php endif ?>
            <span class="media-count"><?= esc($total === 1 ? lang('Media.oneFile') : lang('Media.filesCount', ['count' => $total])) ?></span>
        </div>

        <p class="media-status" x-show="status" x-cloak role="status" x-text="status"></p>

        <?php if ($empty) : ?>
            <div class="panel">
                <?php if ($searching || $state['kind'] !== 'any') : ?>
                    <?= ui('emptyState', ['icon' => 'search', 'title' => lang('Media.noMatchesTitle'), 'text' => lang('Media.noMatchesText')]) ?>
                <?php else : ?>
                    <?= ui('emptyState', ['icon' => 'media', 'title' => lang('Media.emptyTitle'), 'text' => lang('Media.emptyText')]) ?>
                <?php endif ?>
            </div>
        <?php else : ?>
            <ul class="media-grid" role="list">
                <?php foreach ($folders as $folder) : ?>
                    <li class="media-tile is-folder">
                        <a href="<?= attr($folder['url']) ?>" hx-get="<?= attr($folder['url']) ?>" <?= attrs($swap) ?>>
                            <span class="thumb"><?= icon('folder') ?></span>
                            <span class="meta"><span class="name"><?= esc($folder['name']) ?></span><span class="sub"><?= esc(lang('Media.folder')) ?></span></span>
                        </a>
                    </li>
                <?php endforeach ?>
                <?php foreach ($files as $file) : ?>
                    <li class="media-tile">
                        <label>
                            <input type="checkbox" name="ids[]" value="<?= (int) $file['id'] ?>" data-file="<?= attr(json_encode($file, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                            <span class="thumb">
                                <?php if ($file['kind'] === 'image') : ?>
                                    <img src="<?= attr($file['url']) ?>" alt="" loading="lazy" decoding="async">
                                <?php else : ?>
                                    <?= icon($file['kind'] === 'document' ? 'doc' : 'media') ?>
                                    <span class="ext"><?= esc(strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION) ?: $file['kind'])) ?></span>
                                <?php endif ?>
                            </span>
                            <span class="meta"><span class="name"><?= esc($file['name']) ?></span><span class="sub"><?= esc($file['sizeText']) ?></span></span>
                        </label>
                        <a class="open" href="<?= attr($file['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= attr(lang('Media.open', ['name' => $file['name']])) ?>"><?= icon('external', 'sm') ?></a>
                    </li>
                <?php endforeach ?>
            </ul>
            <?= ui('pagination', ['page' => $state['page'], 'perPage' => $per, 'total' => $total, 'href' => $pageHref, 'attrs' => $swap]) ?>
        <?php endif ?>

        <?php if ($picker) : ?>
            <div class="media-picker-foot">
                <span class="muted" x-text="selectedText()"></span>
                <button type="button" class="btn" @click="$dispatch('media-cancel')"><?= esc(lang('Ui.cancel')) ?></button>
                <button type="button" class="btn primary" :disabled="count === 0" @click="choose()"><?= esc(lang('Media.useSelected')) ?></button>
            </div>
        <?php else : ?>
            <div class="float-bar" x-show="count > 0" x-cloak role="region" aria-label="<?= attr(lang('Media.selection')) ?>">
                <span class="state"><span class="dot"></span><span x-text="selectedText()"></span></span>
                <button type="button" class="btn" x-show="count === 1" @click="ask('renameFile')"><?= esc(lang('Media.rename')) ?></button>
                <button type="button" class="btn" x-show="count === 1" @click="copyLink()"><?= esc(lang('Media.copyLink')) ?></button>
                <button type="button" class="btn" @click="ask('move')"><?= esc(lang('Media.move')) ?></button>
                <button type="button" class="btn danger" hx-post="<?= attr($url('delete')) ?>" hx-confirm="<?= attr(lang('Media.confirmDelete')) ?>"><?= esc(lang('Media.delete')) ?></button>
            </div>
        <?php endif ?>

        <dialog class="modal" x-ref="newFolder" aria-labelledby="<?= attr($id) ?>-nf">
            <div class="head"><h2 id="<?= attr($id) ?>-nf"><?= esc(lang('Media.newFolder')) ?></h2></div>
            <div class="content"><div class="field"><label for="<?= attr($id) ?>-nf-name"><?= esc(lang('Media.folderName')) ?></label><input class="input" id="<?= attr($id) ?>-nf-name" name="new_folder" maxlength="100" autocomplete="off" @keydown.enter.prevent="$refs.newFolderGo.click()"></div></div>
            <div class="foot">
                <button type="button" class="btn" @click="$refs.newFolder.close()"><?= esc(lang('Ui.cancel')) ?></button>
                <button type="button" class="btn primary" x-ref="newFolderGo" hx-post="<?= attr($url('folders')) ?>" @click="$refs.newFolder.close()"><?= esc(lang('Media.create')) ?></button>
            </div>
        </dialog>

        <?php if (! $picker) : ?>
            <dialog class="modal" x-ref="renameFolder" aria-labelledby="<?= attr($id) ?>-rf">
                <div class="head"><h2 id="<?= attr($id) ?>-rf"><?= esc(lang('Media.renameFolder')) ?></h2></div>
                <div class="content"><div class="field"><label for="<?= attr($id) ?>-rf-name"><?= esc(lang('Media.folderName')) ?></label><input class="input" id="<?= attr($id) ?>-rf-name" name="folder_name" maxlength="100" value="<?= attr(basename($state['folder'])) ?>" autocomplete="off" @keydown.enter.prevent="$refs.renameFolderGo.click()"></div></div>
                <div class="foot">
                    <button type="button" class="btn" @click="$refs.renameFolder.close()"><?= esc(lang('Ui.cancel')) ?></button>
                    <button type="button" class="btn primary" x-ref="renameFolderGo" hx-post="<?= attr($url('folders/rename')) ?>" @click="$refs.renameFolder.close()"><?= esc(lang('Media.rename')) ?></button>
                </div>
            </dialog>

            <dialog class="modal" x-ref="renameFile" aria-labelledby="<?= attr($id) ?>-rn">
                <div class="head"><h2 id="<?= attr($id) ?>-rn"><?= esc(lang('Media.rename')) ?></h2></div>
                <div class="content"><div class="field"><label for="<?= attr($id) ?>-rn-name"><?= esc(lang('Media.fileName')) ?></label><input class="input" id="<?= attr($id) ?>-rn-name" name="file_name" maxlength="200" autocomplete="off" @keydown.enter.prevent="$refs.renameFileGo.click()"><p class="help"><?= esc(lang('Media.renameHelp')) ?></p></div></div>
                <div class="foot">
                    <button type="button" class="btn" @click="$refs.renameFile.close()"><?= esc(lang('Ui.cancel')) ?></button>
                    <button type="button" class="btn primary" x-ref="renameFileGo" hx-post="<?= attr($url('rename')) ?>" @click="$refs.renameFile.close()"><?= esc(lang('Media.rename')) ?></button>
                </div>
            </dialog>

            <dialog class="modal" x-ref="move" aria-labelledby="<?= attr($id) ?>-mv">
                <div class="head"><h2 id="<?= attr($id) ?>-mv"><?= esc(lang('Media.move')) ?></h2></div>
                <div class="content"><div class="field"><label for="<?= attr($id) ?>-mv-to"><?= esc(lang('Media.moveTo')) ?></label>
                    <select class="select" id="<?= attr($id) ?>-mv-to" name="to">
                        <option value=""><?= esc(lang('Media.topFolder')) ?></option>
                        <?php foreach ($allFolders as $path) : ?><option value="<?= attr($path) ?>"<?= $path === $state['folder'] ? ' selected' : '' ?>><?= esc($path) ?></option><?php endforeach ?>
                    </select></div></div>
                <div class="foot">
                    <button type="button" class="btn" @click="$refs.move.close()"><?= esc(lang('Ui.cancel')) ?></button>
                    <button type="button" class="btn primary" hx-post="<?= attr($url('move')) ?>" @click="$refs.move.close()"><?= esc(lang('Media.move')) ?></button>
                </div>
            </dialog>
        <?php endif ?>
    </form>
</div>
