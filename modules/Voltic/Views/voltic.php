<?php
/**
 * The chat with Voltic. Everything on the screen is drawn by the Alpine component `voltic` of `ui/voltic.js`, which reads what
 * it needs from the JSON block at the end.
 *
 * @var array<string, mixed> $chat The name of the person, the addresses the script calls and the texts it shows.
 */

$version = esc((string) config(\Config\Hyper::class)->appVersion, 'url');
$asset   = static fn (string $path): string => attr(base_url('assets/' . $path) . '?v=' . $version);
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= $asset('ui/voltic.css') ?>">
<script src="<?= $asset('ui/voltic.js') ?>" defer></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Voltic.moduleName')) ?></h1>
        <p><?= esc(lang('Voltic.moduleDescription')) ?></p>
    </div>
</div>

<div class="voltic" x-data="voltic" x-cloak>
    <div class="log" x-ref="log" role="log" aria-live="polite">
        <template x-for="m in messages" :key="m.id">
            <article class="msg" :class="m.role">
                <header>
                    <span class="who" x-text="m.role === 'user' ? username : (m.role === 'error' ? '' : <?= attr(json_encode(lang('Voltic.moduleName'))) ?>)"></span>
                    <span class="muted model" x-show="m.model" x-text="m.model"></span>
                </header>

                <template x-if="m.role === 'user' || m.role === 'error'">
                    <p class="text" x-text="m.text"></p>
                </template>
                <template x-if="m.role === 'ai' || m.role === 'system'">
                    <div class="body" x-html="m.html"></div>
                </template>

                <table class="rows" x-show="m.rows.length">
                    <template x-for="r in m.rows" :key="r.label"><tr><th x-text="r.label"></th><td x-text="r.value"></td></tr></template>
                </table>

                <details class="reasoning" x-show="m.reasoning">
                    <summary><?= esc(lang('Voltic.reasoning')) ?></summary>
                    <p x-text="m.reasoning"></p>
                </details>

                <div class="proposals" x-show="m.proposals.length">
                    <template x-for="(p, i) in m.proposals" :key="i">
                        <div class="proposal">
                            <span class="what" x-text="p.text"></span>
                            <span class="chip bad" x-show="p.refusal" x-text="p.refusal"></span>
                            <span class="chip ok" x-show="p.state === 'done'"><?= esc(lang('Voltic.ran')) ?></span>
                            <button type="button" class="btn primary small" x-show="!p.refusal && p.state !== 'done'" :disabled="p.state === 'running'" @click="run(p)"><?= esc(lang('Voltic.run')) ?></button>
                            <span class="note" x-show="p.note" x-text="p.note"></span>
                        </div>
                    </template>
                </div>

                <div class="links" x-show="m.links.length">
                    <template x-for="l in m.links" :key="l.href"><a class="btn small" :href="l.href" x-text="l.text"></a></template>
                </div>
            </article>
        </template>
        <div class="msg ai thinking" x-show="busy" x-cloak><span class="muted"><?= esc(lang('Voltic.thinking')) ?></span></div>
    </div>

    <form class="compose" @submit.prevent="send()">
        <label class="visually-hidden" for="voltic-input"><?= esc(lang('Voltic.chatInput')) ?></label>
        <textarea id="voltic-input" class="textarea" rows="2" x-ref="input" x-model="input" @keydown="keydown($event)" :disabled="busy"
                  placeholder="<?= attr(lang('Voltic.askVoltic')) ?>" maxlength="4000"></textarea>
        <div class="actions">
            <span class="muted hint"><?= esc(lang('Voltic.shiftEnterAddNewLine')) ?></span>
            <button type="submit" class="btn primary" :disabled="busy || !input.trim()"><?= icon('check') ?><span><?= esc(lang('Voltic.sendChat')) ?></span></button>
        </div>
    </form>
</div>

<script type="application/json" id="voltic-data"><?= json_encode($chat, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?= $this->endSection() ?>
