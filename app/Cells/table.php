<?php
/** @var App\Cells\TableCell $this */
?>
<div class="table-wrap">
    <table class="table">
        <?php if ($caption !== '') : ?><caption class="visually-hidden"><?= esc($caption) ?></caption><?php endif ?>
        <thead>
            <tr>
                <?php if ($selectable) : ?>
                    <th class="select-col" scope="col"><label class="check"><input type="checkbox" aria-label="<?= attr(lang('Ui.selectAll')) ?>" @change="all($event.target.checked)"></label></th>
                <?php endif ?>
                <?php foreach ($columns as $column) : ?>
                    <th scope="col"<?= isset($column['class']) ? ' class="' . attr($column['class']) . '"' : '' ?><?= $this->ariaSort($column) ?>><?= $this->headLink($column) ?></th>
                <?php endforeach ?>
                <th scope="col"><span class="visually-hidden"><?= esc(lang('Ui.actions')) ?></span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <?php if ($selectable) : ?>
                        <td class="select-col"><label class="check"><input type="checkbox" name="ids[]" value="<?= attr((string) ($row['id'] ?? '')) ?>" aria-label="<?= attr(lang('Ui.selectRow', ['row' => $row['label'] ?? ''])) ?>" @change="update()"></label></td>
                    <?php endif ?>
                    <?php foreach ($columns as $column) : ?>
                        <td<?= isset($column['class']) ? ' class="' . attr($column['class']) . '"' : '' ?>><?= $row['cells'][$column['key']] ?? '' ?></td>
                    <?php endforeach ?>
                    <td class="row-actions"><?= $row['actions'] ?? '' ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
