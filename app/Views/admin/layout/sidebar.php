<!-- Sidebar Column -->
<aside class="column is-one-fifth sidebar">
    <nav class="menu">
        <h1 class="title">
            <?= lang("Admin.appName") ?>
        </h1>
        <p class="menu-label">
            <?= lang("Admin.general") ?>
        </p>
        <ul class="menu-list">
            <li>
                <a href="<?= base_url('admin/dashboard') ?>">
                    <span class="icon">
                        <i class="fa-solid fa-house"></i>
                    </span>
                    <?= lang("Admin.dashboard") ?>
                </a>
            </li>
            <li>
                <a href="<?= base_url('admin/models') ?>">
                    <span class="icon">
                        <i class=" fa-solid fa-circle-nodes"></i>
                    </span>
                    <?= lang("Admin.models") ?>
                </a>
            </li>
            <li>
                <a href="<?= base_url('admin/entries') ?>">
                    <span class="icon">
                        <i class=" fa-solid fa-table-list"></i>
                    </span>
                    <?= lang("Admin.entries") ?>
                </a>
            </li>
        </ul>
        <p class="menu-label">
            <?= lang("Admin.others") ?>
        </p>
        <ul class="menu-list">
            <li>
                <a href="<?= base_url('admin/settings') ?>">
                    <span class="icon">
                        <i class=" fa-solid fa-cog"></i>
                    </span>
                    <?= lang("Admin.settings") ?>
                </a>
            </li>
        </ul>
    </nav>
</aside>