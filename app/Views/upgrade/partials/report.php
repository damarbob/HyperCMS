<?php
/**
 * What the preflight found in the old tables, in the order the command prints it.
 *
 * @var array<string, mixed> $r
 */
$bytes = static fn (int $n): string => $n >= 1048576 ? round($n / 1048576, 1) . ' MB' : round($n / 1024, 1) . ' KB';
$ids   = static fn (array $list): string => lang('Upgrade.idList', ['count' => count($list), 'ids' => implode(', ', array_slice($list, 0, 20)) . (count($list) > 20 ? ' ...' : '')]);
?>
<section class="stack">
    <h2><?= esc(lang('Upgrade.reportTitle')) ?></h2>
    <ul>
        <li><?= esc(lang('Upgrade.layout', ['layout' => $r['layout']])) ?></li>
        <li><?= esc(lang('Upgrade.server', ['version' => $r['server']['version']])) ?></li>
        <li><?= esc(lang('Upgrade.packet', ['max' => $bytes((int) $r['packet']['maxAllowed']), 'largest' => $bytes((int) $r['packet']['largestPayload'])])) ?></li>
    </ul>

    <?php if (! $r['server']['supported']) : ?>
        <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc(lang('Upgrade.serverOld')) ?></span></div>
    <?php endif ?>
    <?php if (! $r['packet']['fits']) : ?>
        <div class="alert warn"><?= icon('alert') ?><span><?= esc(lang('Upgrade.packetTight')) ?></span></div>
    <?php endif ?>

    <h3><?= esc(lang('Upgrade.entriesPerModel')) ?></h3>
    <ul>
        <?php foreach ($r['models'] as $model) : ?>
            <li><?= esc(lang('Upgrade.modelRow', ['id' => $model['id'], 'name' => $model['name'], 'live' => $model['live'], 'trashed' => $model['trashed']])) ?></li>
        <?php endforeach ?>
    </ul>

    <?php if ($r['entriesWithoutVersion'] > 0) : ?>
        <div class="alert warn"><?= icon('alert') ?><span><?= esc(lang('Upgrade.withoutVersion', ['count' => $r['entriesWithoutVersion']])) ?></span></div>
    <?php endif ?>
    <?php if ($r['oddVersions'] !== []) : ?>
        <div class="alert warn"><?= icon('alert') ?><span><?= esc(lang('Upgrade.oddVersions', ['ids' => $ids($r['oddVersions'])])) ?></span></div>
    <?php endif ?>
    <?php if ($r['notObjectVersions'] !== []) : ?>
        <div class="alert warn"><?= icon('alert') ?><span><?= esc(lang('Upgrade.notObject', ['ids' => $ids($r['notObjectVersions'])])) ?></span></div>
    <?php endif ?>
    <?php if ($r['repeatedVersions'] !== []) : ?>
        <div class="alert warn"><?= icon('alert') ?><span><?= esc(lang('Upgrade.repeated', ['ids' => $ids($r['repeatedVersions'])])) ?></span></div>
    <?php endif ?>

    <?php foreach ($r['fieldProblems'] as $problem) : ?>
        <div class="alert warn"><?= icon('alert') ?><span><?= esc(lang('Upgrade.fieldProblem', ['model' => $problem['model'], 'problem' => $problem['problem']])) ?></span></div>
    <?php endforeach ?>

    <?php if ($r['unconvertible'] !== []) : ?>
        <h3><?= esc(lang('Upgrade.unconvertible')) ?></h3>
        <ul>
            <?php foreach ($r['unconvertible'] as $field) : ?>
                <li><?= esc(lang('Upgrade.unconvertibleRow', ['model' => $field['model'], 'field' => $field['field'], 'problem' => $field['problem']])) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
