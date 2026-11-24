<?php
/**
 * The install page. It asks for the database, the address of the site and the first account, and writes the settings file.
 *
 * @var array<string, string> $input
 * @var array<string, string> $errors
 * @var list<array{key: string, ok: bool, detail: string}> $checks
 * @var bool $ready
 * @var string $keyPath
 */
$this->setVar('title', lang('Install.title'));
$this->setVar('wide', true);
$field = static fn (string $id, string $label, array $extra = []): string => ui('field', array_merge([
    'id'    => $id,
    'label' => $label,
    'value' => $input[$id] ?? '',
    'error' => $errors[$id] ?? '',
], $extra));
?>
<?= $this->extend('auth/layout/bare') ?>

<?= $this->section('content') ?>
<div>
    <h1><?= esc(lang('Install.title')) ?></h1>
    <p class="lede"><?= esc(lang('Install.lede')) ?></p>
</div>

<section class="stack" aria-labelledby="req-title">
    <h2 id="req-title"><?= esc(lang('Install.reqTitle')) ?></h2>
    <ul class="checklist">
        <?php foreach ($checks as $check) : ?>
            <li>
                <span class="chip <?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'OK' : '!' ?></span>
                <?= esc(lang('Install.req.' . $check['key'], ['detail' => $check['detail']])) ?>
            </li>
        <?php endforeach ?>
    </ul>
    <?php if (! $ready) : ?>
        <div class="alert warn" role="alert"><?= icon('alert') ?><span><?= esc(lang('Install.unmet')) ?></span></div>
    <?php endif ?>
</section>

<form class="stack" action="<?= attr(site_url('install')) ?>" method="post" autocomplete="off">
    <?= csrf_field() ?>

    <h2><?= esc(lang('Install.keyTitle')) ?></h2>
    <?= $field('key', lang('Install.keyLabel'), ['required' => true, 'help' => lang('Install.keyHelp', ['path' => $keyPath]), 'value' => '']) ?>

    <h2><?= esc(lang('Install.dbTitle')) ?></h2>
    <?= $field('db_host', lang('Install.dbHost'), ['required' => true]) ?>
    <?= $field('db_port', lang('Install.dbPort'), ['required' => true, 'kind' => 'number']) ?>
    <?= $field('db_name', lang('Install.dbName'), ['required' => true]) ?>
    <?= $field('db_user', lang('Install.dbUser'), ['required' => true]) ?>
    <?= $field('db_password', lang('Install.dbPassword'), ['kind' => 'password', 'value' => '', 'autocomplete' => 'off']) ?>
    <?= $field('db_prefix', lang('Install.dbPrefix'), ['help' => lang('Install.dbPrefixHelp')]) ?>

    <h2><?= esc(lang('Install.siteTitle')) ?></h2>
    <?= $field('base_url', lang('Install.baseUrl'), ['required' => true, 'kind' => 'url', 'help' => lang('Install.baseUrlHelp')]) ?>

    <h2><?= esc(lang('Install.adminTitle')) ?></h2>
    <?= $field('admin_username', lang('Install.adminUsername'), ['required' => true, 'autocomplete' => 'off']) ?>
    <?= $field('admin_email', lang('Install.adminEmail'), ['required' => true, 'kind' => 'email', 'autocomplete' => 'off']) ?>
    <?= $field('admin_password', lang('Install.adminPassword'), ['required' => true, 'kind' => 'password', 'value' => '', 'autocomplete' => 'new-password', 'help' => lang('Install.adminPasswordHelp')]) ?>
    <?= $field('admin_password_confirm', lang('Install.adminPasswordConfirm'), ['required' => true, 'kind' => 'password', 'value' => '', 'autocomplete' => 'new-password']) ?>

    <h2><?= esc(lang('Install.tickTitle')) ?></h2>
    <?= $field('tick', lang('Install.tickLabel'), [
        'kind'    => 'select',
        'options' => ['cron' => lang('Install.tickCron'), 'url' => lang('Install.tickUrl'), 'later' => lang('Install.tickLater')],
        'help'    => lang('Install.tickHelp'),
    ]) ?>

    <?= ui('button', ['label' => lang('Install.submit'), 'type' => 'submit', 'variant' => 'primary', 'disabled' => ! $ready]) ?>
</form>
<?= $this->endSection() ?>
