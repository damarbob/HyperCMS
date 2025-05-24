<?php

/** @var \Config\Hyper */
$hyperConfig = config('hyper');
/** @var \App\Services\HyperHooks */
$hooks = service('hooks');
helper('hyper_url');
?>
<!-- Sidebar Column -->
<aside class="column sidebar">
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
        <?php foreach ($menu as $group => $items): ?>
            <?php if (!empty($group)): ?>
                <p class="menu-label">
                    <?= $group ?>
                </p>
            <?php endif ?>

            <?php if (!empty($items)): ?>
                <ul class="menu-list">
                    <?php foreach ($items as $id => $item): ?>
                        <?php $hasSubmenu = !empty($item['submenu']); ?>
                        <li class="<?= $hasSubmenu ? (url_contains(normalize_url($uri),  $item['url']) ? 'is-active' : '') : '' ?>">
                            <a
                                class="<?= url_contains(normalize_url($uri), $item['url']) ? 'is-active' : '' ?> <?= $hasSubmenu ? 'has-submenu' : '' ?>"
                                <?= !$hasSubmenu ? "href='{$item['url']}'" : '' ?>
                                data-tippy-content="<?= $item['tooltip_content'] ?>"
                                data-tippy-placement="<?= $item['tooltip_placement'] ?>">
                                <span class="icon">
                                    <i class="<?= $item['icon'] ?>"></i>
                                </span>
                                <span class="text">
                                    <?= $item['text'] ?>
                                </span>
                            </a>
                            <?php if ($hasSubmenu): ?>
                                <ul>
                                    <?php foreach ($item['submenu'] as $subItemId => $subItem): ?>
                                        <li>
                                            <a class="<?= urls_match(normalize_url($uri), $subItem['url']) ? 'is-active' : '' ?>" href="<?= $subItem['url'] ?>" data-tippy-content="<?= $subItem['tooltip_content'] ?>" data-tippy-placement="<?= $subItem['tooltip_placement'] ?>">
                                                <span class="text">
                                                    <?= $subItem['text'] ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        <?php endforeach ?>
    </nav>
</aside>
<div class="sidebar-overlay" onclick="toggleActive(document.querySelector('.sidebar'))"></div>