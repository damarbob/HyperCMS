<?php

helper('hyper_url');

/** @var \Config\Hyper */
$hyperConfig = config('hyper');
/** @var \App\Services\HyperHooks */
$hooks = service('hooks');
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
            <?php if (!empty($items)): ?>

                <?php if (!empty($group)): ?>
                    <!-- Show menu label if group and items exist -->
                    <p class="menu-label">
                        <?= $group ?>
                    </p>
                <?php endif ?>

                <ul class="menu-list">
                    <?php foreach ($items as $id => $item): ?>
                        <?php $hasSubmenu = !empty($item['submenu']); ?>
                        <li class="<?= $hasSubmenu ? (url_contains(normalize_url($uri),  $item['url']) ? 'is-active' : '') : '' ?>">
                            <a
                                class="<?= implode(" ", [url_contains(normalize_url($uri), $item['url']) ? 'is-active' : '', $hasSubmenu ? 'has-submenu' : '']) ?>"
                                <?= !$hasSubmenu ? "href='{$item['url']}'" : '' ?>
                                data-tippy-content="<?= $item['tooltip_content'] ?>"
                                data-tippy-placement="<?= $item['tooltip_placement'] ?>">
                                <?php if (!empty($item['icon'])): ?>
                                    <!-- Show icon if exists -->
                                    <span class="icon">
                                        <i class="<?= $item['icon'] ?>"></i>
                                    </span>
                                <?php else: ?>
                                    <!-- Use default icon -->
                                    <span class="icon">
                                        <i class="fa-solid fa-question"></i>
                                    </span>
                                <?php endif ?>
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