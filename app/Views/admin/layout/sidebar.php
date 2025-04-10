<!-- Sidebar Column -->
<aside class="column sidebar is-collapsed">
    <nav class="menu">
        <div class="brand py-3">
            <h1 class="title">
                <?= lang("Admin.appName") ?>
            </h1>
        </div>
        <div class="brand-collapsed py-3">
            <h1 class="title has-text-centered">
                <?= substr(lang("Admin.appName"), 0, 1) ?>
            </h1>
        </div>
        <p class="menu-label">
            <?= lang("Admin.general") ?>
        </p>
        <ul class="menu-list">
            <?php if (ENVIRONMENT === 'development'): // @IMPORTANT: Update sidebar menu if dashboard is finished 
            ?>
                <?php for ($i = 0; $i < 1; $i++): // For overflow testing 
                ?>
                    <li>
                        <a href="<?= base_url('admin/dashboard') ?>" data-tippy-content="<?= lang("Admin.dashboard") ?>" data-tippy-placement="right">
                            <span class="icon">
                                <i class="fa-solid fa-house"></i>
                            </span>
                            <span class="text">
                                <?= lang("Admin.dashboard") ?>
                            </span>
                        </a>
                    </li>
                <?php endfor; ?>
            <?php endif; ?>
            <li>
                <a href="<?= base_url('admin/models') ?>" data-tippy-content="<?= lang("Admin.models") ?>" data-tippy-placement="right">
                    <span class="icon">
                        <i class=" fa-solid fa-circle-nodes"></i>
                    </span>
                    <span class="text">
                        <?= lang("Admin.models") ?>
                    </span>
                </a>
            </li>
            <li>
                <a href="<?= base_url('admin/entries') ?>" data-tippy-content="<?= lang("Admin.entries") ?>" data-tippy-placement="right">
                    <span class="icon">
                        <i class=" fa-solid fa-table-list"></i>
                    </span>
                    <span class="text">
                        <?= lang("Admin.entries") ?>
                    </span>
                </a>
            </li>
            <li>
                <a href="<?= base_url('admin/file-manager') ?>" data-tippy-content="<?= lang("Admin.fileManager") ?>" data-tippy-placement="right">
                    <span class="icon">
                        <i class="fa-solid fa-folder-closed"></i>
                    </span>
                    <span class="text">
                        <?= lang("Admin.fileManager") ?>
                    </span>
                </a>
            </li>
        </ul>
        <?php if ($models) : ?>
            <p class="menu-label">
                <?= lang("Admin.models") ?>
            </p>
            <ul class="menu-list">
                <?php foreach ($models as $model) : ?>
                    <li>
                        <a href="<?= base_url('admin/model?id=' . $model['id']) ?>" data-tippy-content="<?= $model['name'] ?>" data-tippy-placement="right">
                            <span class="icon">
                                <i class=" fa-solid fa-box-open"></i>
                            </span>
                            <span class="text">
                                <?= $model['name'] ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (ENVIRONMENT === 'development'): // @IMPORTANT: Update sidebar menu if settings is finished 
        ?>
            <p class="menu-label">
                <?= lang("Admin.others") ?>
            </p>
            <ul class="menu-list">
                <li>
                    <a href="<?= base_url('admin/settings') ?>" data-tippy-content="<?= lang("Admin.settings") ?>" data-tippy-placement="right">
                        <span class="icon">
                            <i class=" fa-solid fa-cog"></i>
                        </span>
                        <span class="text">
                            <?= lang("Admin.settings") ?>
                        </span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>
</aside>
<div class="sidebar-overlay" onclick="toggleCollapse(document.querySelector('.sidebar'))"></div>