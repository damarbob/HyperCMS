<li>
    <a class="<?= normalize_url($uri) === base_url('admin/settings/paging-system') ? 'is-active' : '' ?>" href="<?= base_url('admin/settings/paging-system') ?>" data-tippy-content="<?= lang("pagingsystem.moduleName") ?>" data-tippy-placement="right">
        <span class="text">
            <?= lang("pagingsystem.moduleName") ?>
        </span>
    </a>
</li>