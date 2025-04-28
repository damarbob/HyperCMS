<?php

/** @var \Config\Hyper */
$hyperConfig = config('hyper');
/** @var \App\Services\HyperHooks */
$hooks = service('hooks');
helper('hyper_url');
?>
<!-- Sidebar Column -->
<aside class="column sidebar is-collapsed">
    <nav class="menu">
        <div class="brand py-3">
            <h1 class="title">
                <?= $hyperConfig->appName ?>
            </h1>
        </div>
        <div class="brand-collapsed py-3">
            <h1 class="title has-text-centered">
                <?= substr($hyperConfig->appName, 0, 1) ?>
            </h1>
        </div>
        <p class="menu-label">
            <?= lang("Admin.general") ?>
        </p>
        <ul class="menu-list">
            <?php foreach ($menu as $item): ?>
                <li>
                    <a
                        class="<?= url_contains(normalize_url($uri), $item['url']) ? 'is-active' : '' ?>"
                        href="<?= $item['url'] ?>"
                        data-tippy-content="<?= $item['tooltip_content'] ?>"
                        data-tippy-placement="<?= $item['tooltip_placement'] ?>">
                        <span class="icon">
                            <i class="<?= $item['icon'] ?>"></i>
                        </span>
                        <span class="text">
                            <?= $item['text'] ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($models) : ?>
            <p class="menu-label">
                <?= lang("Admin.models") ?>
            </p>
            <ul class="menu-list">
                <?php foreach ($models as $model) : ?>
                    <li>
                        <a class="<?= url_contains(normalize_url($uri), base_url('admin/model?id=' . $model['id'])) ? 'is-active' : '' ?>" href="<?= base_url('admin/model?id=' . $model['id']) ?>" data-tippy-content="<?= $model['name'] ?>" data-tippy-placement="right">
                            <span class="icon">
                                <i class="<?= !empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open' ?>"></i>
                            </span>
                            <span class="text">
                                <?= $model['name'] ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="menu-label">
            <?= lang("Admin.others") ?>
        </p>
        <ul class="menu-list">
            <li class="<?= url_contains(normalize_url($uri), base_url('admin/settings')) ? 'is-active' : '' ?>">
                <a class="has-submenu <?= url_contains(normalize_url($uri), base_url('admin/settings')) ? 'is-active' : '' ?>" data-tippy-content="<?= lang("Admin.settings") ?>" data-tippy-placement="right">
                    <span class="icon">
                        <i class=" fa-solid fa-cog"></i>
                    </span>
                    <span class="text">
                        <?= lang("Admin.settings") ?>
                    </span>
                </a>
                <ul>
                    <li>
                        <a class="<?= urls_match(normalize_url($uri), base_url('admin/settings')) ? 'is-active' : '' ?>" href="<?= base_url('admin/settings') ?>" data-tippy-content="<?= lang("Admin.general") ?>" data-tippy-placement="right">
                            <span class="text">
                                <?= lang("Admin.general") ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="<?= urls_match(normalize_url($uri), base_url('admin/settings/models')) ? 'is-active' : '' ?>" href="<?= base_url('admin/settings/models') ?>" data-tippy-content="<?= lang("Admin.models") ?>" data-tippy-placement="right">
                            <span class="text">
                                <?= lang("Admin.models") ?>
                            </span>
                        </a>
                    </li>
                    <?= $hooks->trigger(hook('backend.part:view:sidebar:settings')) // Add your custom settings here 
                    ?>
                </ul>
            </li>
        </ul>
    </nav>
</aside>
<div class="sidebar-overlay" onclick="toggleCollapse(document.querySelector('.sidebar'))"></div>