<?php
/**
 * What the install did, step by step. On success it is the last page the install ever shows.
 *
 * @var list<array{step: string, ok: bool, detail: string}> $report
 * @var bool $ok
 * @var string|null $tickUrl
 * @var string $tick
 * @var string $spark
 */
$title = $ok ? lang('Install.doneTitle') : lang('Install.failedTitle');
$this->setVar('title', $title);
?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc($title) ?></h1>
    <p class="lede"><?= esc($ok ? lang('Install.doneLede') : lang('Install.failedLede')) ?></p>
</div>

<ul class="checklist">
    <?php foreach ($report as $step) : ?>
        <li>
            <span class="chip <?= $step['ok'] ? 'ok' : 'bad' ?>"><?= $step['ok'] ? 'OK' : '!' ?></span>
            <?= esc(lang('Install.step.' . $step['step'], ['detail' => $step['ok'] ? $step['detail'] : ''])) ?>
        </li>
    <?php endforeach ?>
</ul>

<?php foreach ($report as $step) : ?>
    <?php if (! $step['ok'] && $step['detail'] !== '') : ?>
        <div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($step['detail']) ?></span></div>
    <?php endif ?>
<?php endforeach ?>

<?php if ($ok) : ?>
    <section class="stack">
        <h2><?= esc(lang('Install.nextTitle')) ?></h2>
        <?php if ($tick === 'url' && $tickUrl !== null) : ?>
            <p><?= esc(lang('Install.nextUrl')) ?></p>
            <pre class="code"><?= esc($tickUrl) ?></pre>
        <?php elseif ($tick === 'cron') : ?>
            <p><?= esc(lang('Install.nextCron')) ?></p>
            <pre class="code"><?= esc('* * * * * php ' . $spark . ' hyper:tick --budget 50') ?></pre>
        <?php else : ?>
            <p><?= esc(lang('Install.nextLater')) ?></p>
        <?php endif ?>
    </section>
    <?= ui('button', ['label' => lang('Install.signIn'), 'href' => url_to('login'), 'variant' => 'primary']) ?>
<?php else : ?>
    <form class="stack" action="<?= attr(site_url('install/run')) ?>" method="post">
        <?= csrf_field() ?>
        <?= ui('button', ['label' => lang('Install.retry'), 'type' => 'submit', 'variant' => 'primary']) ?>
    </form>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('foot') ?>
<?php if (! $ok) : ?><a href="<?= attr(site_url('install')) ?>"><?= esc(lang('Install.startOver')) ?></a><?php endif ?>
<?= $this->endSection() ?>
