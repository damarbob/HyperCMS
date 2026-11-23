<?php
/**
 * The people who can sign in. The search and the role filter above the list are a form that asks htmx for the list again
 * as they change, and the list itself is the partial `partials/results`.
 *
 * @var array<string, mixed>  $request
 * @var array<string, string> $urls
 * @var array<string, string> $roleOptions
 * @var int                   $total
 */

$hidden = '';
foreach (['sort' => $request['sort'] !== 'username' || $request['dir'] !== 'asc' ? $request['sort'] : '', 'dir' => $request['sort'] !== 'username' || $request['dir'] !== 'asc' ? $request['dir'] : '', 'per' => $request['per'] !== 25 ? $request['per'] : ''] as $name => $value) {
    if ($value !== '') {
        $hidden .= '<input type="hidden" name="' . attr($name) . '" value="' . attr((string) $value) . '">';
    }
}

$select = '<select class="select" name="role" aria-label="' . attr(lang('UserManagement.filterByRole')) . '"><option value="">' . esc(lang('UserManagement.anyRole')) . '</option>';
foreach ($roleOptions as $name => $label) {
    $select .= '<option value="' . attr($name) . '"' . ($request['role'] === $name ? ' selected' : '') . '>' . esc($label) . '</option>';
}
$select .= '</select>';
?>
<?= $this->extend('admin/layout/app') ?>

<?= $this->section('content') ?>
<div class="page-head">
    <div>
        <h1><?= esc(lang('UserManagement.moduleName')) ?></h1>
        <p><?= esc($total === 1 ? lang('UserManagement.onePerson') : lang('UserManagement.peopleCount', ['count' => $total])) ?></p>
    </div>
    <div class="actions">
        <?= ui('button', ['label' => lang('UserManagement.newUser'), 'href' => $urls['new'], 'variant' => 'primary', 'icon' => 'plus']) ?>
    </div>
</div>

<?= ui('filterBar', [
    'action'      => $urls['list'],
    'searchLabel' => lang('UserManagement.search'),
    'q'           => $request['q'],
    'target'      => '#users',
    'fields'      => $hidden . $select,
]) ?>

<?= view('UserManagement\Views\partials\results', get_defined_vars()) ?>
<?= $this->endSection() ?>
