<?php
/**
 * The access tokens of the signed-in person.
 *
 * @var list<array<string, mixed>>        $columns
 * @var list<array<string, mixed>>        $rows
 * @var array{name: string, scopes: list<string>} $values
 * @var string                            $error
 * @var list<string>                      $scopes
 * @var array<string, string>|null        $newToken
 */
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('Tokens.title')) ?></h1>
        <p><?= esc(lang('Tokens.text')) ?></p>
    </div>
    <?php if ($rows !== []) : ?>
        <div class="actions">
            <form method="post" action="<?= attr(base_url('admin/tokens/revoke-all')) ?>" onsubmit="return confirm(<?= attr(json_encode(lang('Tokens.confirmRevokeAll'), JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <?= ui('button', ['label' => lang('Tokens.revokeAll'), 'type' => 'submit', 'variant' => 'danger', 'icon' => 'trash']) ?>
            </form>
        </div>
    <?php endif ?>
</div>

<?php if (is_array($newToken)) : ?>
    <div class="alert ok" role="status">
        <?= icon('key') ?>
        <div class="stack" style="gap: var(--s2); min-width: 0">
            <strong><?= esc(lang('Tokens.created', ['name' => $newToken['name']])) ?></strong>
            <span><?= esc(lang('Tokens.copyNow')) ?></span>
            <code class="token-value" tabindex="0"><?= esc($newToken['token']) ?></code>
            <span class="muted"><?= esc(lang('Tokens.validUntil', ['date' => $newToken['expires']])) ?></span>
        </div>
    </div>
<?php endif ?>

<?php if ($rows === []) : ?>
    <div class="panel"><?= ui('emptyState', ['icon' => 'key', 'title' => lang('Tokens.noneTitle'), 'text' => lang('Tokens.noneText')]) ?></div>
<?php else : ?>
    <?= ui('table', ['caption' => lang('Tokens.title'), 'columns' => $columns, 'rows' => $rows]) ?>
<?php endif ?>

<section class="panel" aria-labelledby="token-new" style="margin-top: var(--s5); max-width: 760px">
    <header><h2 id="token-new"><?= esc(lang('Tokens.newTitle')) ?></h2></header>
    <form class="body stack" method="post" action="<?= attr(base_url('admin/tokens')) ?>">
        <?= csrf_field() ?>
        <?php if ($error !== '') : ?><div class="alert" role="alert"><?= icon('alert') ?><span><?= esc($error) ?></span></div><?php endif ?>
        <div class="field">
            <label for="t-name"><?= esc(lang('Tokens.name')) ?><span class="req" aria-hidden="true">*</span></label>
            <input id="t-name" class="input" type="text" name="name" value="<?= attr($values['name']) ?>" required maxlength="100" autocomplete="off" aria-describedby="t-name-help">
            <p class="help" id="t-name-help"><?= esc(lang('Tokens.nameHelp')) ?></p>
        </div>
        <fieldset class="field" style="border: 0; padding: 0; margin: 0">
            <legend><?= esc(lang('Tokens.scopes')) ?></legend>
            <div class="options">
                <?php foreach ($scopes as $scope) : ?>
                    <label class="check"><input type="checkbox" name="scopes[]" value="<?= attr($scope) ?>"<?= in_array($scope, $values['scopes'], true) ? ' checked' : '' ?>> <span><?= esc(lang('Tokens.scope_' . str_replace('.', '_', $scope))) ?> <code><?= esc($scope) ?></code></span></label>
                <?php endforeach ?>
            </div>
            <p class="help"><?= esc(lang('Tokens.scopesHelp')) ?></p>
        </fieldset>
        <div><?= ui('button', ['label' => lang('Tokens.create'), 'type' => 'submit', 'variant' => 'primary', 'icon' => 'plus']) ?></div>
    </form>
</section>
<?= $this->endSection() ?>
