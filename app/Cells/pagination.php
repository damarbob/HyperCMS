<?php
/** @var App\Cells\PaginationCell $this */
$current = $this->current();
$last    = $this->pageCount();
?>
<div class="pager">
    <span><?= $total === 0 ? esc(lang('Ui.nothingToShow')) : esc(lang('Ui.showing', ['from' => $this->from(), 'to' => $this->to(), 'total' => $total])) ?></span>
    <?php if ($last > 1) : ?>
        <ul class="pages" aria-label="<?= attr(lang('Ui.pages')) ?>">
            <li><?= $current > 1 ? $this->link($current - 1, lang('Ui.previous')) : '<span aria-disabled="true">' . esc(lang('Ui.previous')) . '</span>' ?></li>
            <?php foreach ($this->window() as $number) : ?>
                <li><?= $number === null ? '<span aria-hidden="true">&hellip;</span>' : $this->link($number, (string) $number, $number === $current) ?></li>
            <?php endforeach ?>
            <li><?= $current < $last ? $this->link($current + 1, lang('Ui.next')) : '<span aria-disabled="true">' . esc(lang('Ui.next')) . '</span>' ?></li>
        </ul>
    <?php endif ?>
</div>
