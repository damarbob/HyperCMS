<?php
/** @var App\Cells\ToastsCell $this */
?>
<div class="toasts" role="region" aria-label="<?= attr(lang('Ui.notifications')) ?>" aria-live="polite"
    x-data x-init='JSON.parse($el.dataset.initial || "[]").forEach(t => $store.toasts.push(t.type, t.message))' data-initial="<?= $this->initialJson() ?>">
    <template x-for="item in $store.toasts.items" :key="item.id">
        <div class="toast toast-enter" :class="item.type">
            <svg class="icon" aria-hidden="true" focusable="false"><use :href="'<?= attr((string) parse_url(base_url('assets/ui/icons.svg'), PHP_URL_PATH)) ?>#' + (item.type === 'bad' ? 'alert' : (item.type === 'warn' ? 'info' : 'check'))"/></svg>
            <span x-text="item.message"></span>
            <button type="button" @click="$store.toasts.remove(item.id)" aria-label="<?= attr(lang('Ui.close')) ?>"><?= icon('x', 'sm') ?></button>
        </div>
    </template>
</div>
