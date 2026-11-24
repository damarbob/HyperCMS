<?php
/**
 * Where the upgrade stands. While there is more to do, this piece asks for the next batch by itself and replaces itself with the
 * answer, so the browser keeps the upgrade going until it is done or stops.
 *
 * @var array<string, mixed> $progress
 * @var bool                 $busy
 */
$phase  = (string) $progress['phase'];
$failed = $progress['failed'];
$done   = $phase === 'done';
$active = ! $done && $failed === null;
$title  = $done ? lang('Upgrade.doneTitle') : ($failed !== null ? lang('Upgrade.failedTitle') : lang('Upgrade.progressTitle'));
$token  = json_encode([config('Security')->headerName => csrf_hash()], JSON_THROW_ON_ERROR);
?>
<div id="upgrade-progress" class="stack" data-phase="<?= esc($phase, 'attr') ?>"<?= $failed !== null ? ' data-failed' : '' ?><?php if ($active) : ?> hx-post="<?= attr(site_url('upgrade/run')) ?>" hx-trigger="load delay:300ms" hx-target="this" hx-swap="outerHTML" hx-headers='<?= esc($token, 'attr') ?>'<?php endif ?>>
    <h2><?= esc($title) ?></h2>

    <?php if ($done) : ?>
        <p class="lede"><?= esc(lang('Upgrade.doneLede')) ?></p>
    <?php elseif ($failed !== null) : ?>
        <p class="lede"><?= esc(lang('Upgrade.failedLede')) ?></p>
        <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc((string) $failed) ?></span></div>
    <?php endif ?>

    <ul class="checklist">
        <?php foreach ($progress['log'] as $line) : ?>
            <li><span class="chip ok">OK</span> <?= esc((string) $line) ?></li>
        <?php endforeach ?>
        <?php foreach ($progress['steps'] as $step) : ?>
            <li><span class="chip ok">OK</span> <?= esc(lang('Upgrade.stepRow', ['number' => $step['number'], 'name' => $step['name'], 'changed' => $step['changed']])) ?></li>
            <?php foreach (array_slice($step['problems'], 0, 20) as $problem) : ?>
                <li class="lede"><?= esc((string) $problem) ?></li>
            <?php endforeach ?>
        <?php endforeach ?>
        <?php if (($progress['notes']['odd'] ?? 0) > 0) : ?>
            <li><span class="chip warn">!</span> <?= esc(lang('Upgrade.noteOdd', ['count' => $progress['notes']['odd']])) ?></li>
        <?php endif ?>
        <?php if (($progress['notes']['repeated'] ?? 0) > 0) : ?>
            <li><span class="chip warn">!</span> <?= esc(lang('Upgrade.noteRepeated', ['count' => $progress['notes']['repeated']])) ?></li>
        <?php endif ?>
    </ul>

    <?php if ($active) : ?>
        <p><?= esc($busy ? lang('Upgrade.busy') : lang('Upgrade.working', ['phase' => lang('Upgrade.phase.' . $phase)])) ?></p>
        <noscript>
            <p><?= esc(lang('Upgrade.noScript')) ?></p>
            <form action="<?= attr(site_url('upgrade/run')) ?>" method="post">
                <?= csrf_field() ?>
                <?= ui('button', ['label' => lang('Upgrade.continue'), 'type' => 'submit', 'variant' => 'primary']) ?>
            </form>
        </noscript>
    <?php elseif ($failed !== null) : ?>
        <form action="<?= attr(site_url('upgrade/restart')) ?>" method="post" hx-post="<?= attr(site_url('upgrade/restart')) ?>" hx-target="#upgrade-progress" hx-swap="outerHTML">
            <?= csrf_field() ?>
            <?= ui('button', ['label' => lang('Upgrade.retry'), 'type' => 'submit', 'variant' => 'primary']) ?>
        </form>
    <?php elseif ($done) : ?>
        <?= ui('button', ['label' => lang('Upgrade.signIn'), 'href' => url_to('login'), 'variant' => 'primary']) ?>
    <?php endif ?>
</div>
