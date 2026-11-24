<?php
/**
 * The upgrade page after the key was typed. Before the first batch it shows what the preflight found and a start button.
 * Once the job has begun it shows the progress, which asks for its own next batch.
 *
 * @var bool                    $started
 * @var array<string, mixed>    $progress
 * @var array<string, mixed>|null $report
 * @var list<string>            $pending
 * @var bool                    $busy
 */
$this->setVar('title', lang('Upgrade.title'));
$this->setVar('wide', true);
?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Upgrade.title')) ?></h1>
    <p class="lede"><?= esc(lang('Upgrade.lede')) ?></p>
</div>

<script src="<?= attr(base_url('assets/vendor/htmx.org/dist/htmx.min.js')) ?>" defer></script>

<?php if ($started) : ?>
    <?= view('upgrade/partials/progress', ['progress' => $progress, 'busy' => $busy]) ?>
<?php else : ?>
    <div id="upgrade-start" class="stack">
        <?php if ($report !== null) : ?>
            <?= view('upgrade/partials/report', ['r' => $report]) ?>
        <?php endif ?>

        <?php if ($pending !== []) : ?>
            <section class="stack">
                <h2><?= esc(lang('Upgrade.pendingTitle')) ?></h2>
                <ul>
                    <?php foreach ($pending as $name) : ?><li><?= esc($name) ?></li><?php endforeach ?>
                </ul>
                <p class="lede"><?= esc(lang('Upgrade.pendingNote')) ?></p>
            </section>
        <?php endif ?>

        <p><?= esc(lang('Upgrade.backup')) ?></p>

        <?php $blocked = $report !== null && ! $report['server']['supported']; ?>
        <form action="<?= attr(site_url('upgrade/run')) ?>" method="post" hx-post="<?= attr(site_url('upgrade/run')) ?>" hx-target="#upgrade-start" hx-swap="outerHTML">
            <?= csrf_field() ?>
            <?= ui('button', ['label' => lang('Upgrade.start'), 'type' => 'submit', 'variant' => 'primary', 'disabled' => $blocked]) ?>
        </form>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
