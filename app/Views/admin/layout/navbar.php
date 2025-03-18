<?php

use function app\Helpers\isIntegerString;

helper('type_checking');
?>
<!-- Navbar -->
<nav class="navbar" role="navigation" aria-label="main navigation">
    <div class="navbar-brand">
        <div class="navbar-item">
            <!-- Sidebar toggle button -->
            <div class="buttons">
                <button id="sidebarToggle" class="button is-primary">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
        <div class="navbar-item">
            <h1 class="navbar-item">
                <strong><?= $title ?></strong>
            </h1>
            <nav class="breadcrumb has-succeeds-separator" aria-label="breadcrumbs">
                <ul>
                    <?php $link = ''; ?>
                    <?php for ($i = 0; $i < count($uriSegments); $i++): ?>
                        <?php
                        // Append the current segment to the running link.
                        // Adjust with a trailing slash if needed.
                        $link .= $uriSegments[$i] . '/';
                        ?>
                        <?php if ($i == count($uriSegments) - 1): // The last segment 
                        ?>
                            <li class="is-active">
                                <a href="<?= base_url($link) ?>" aria-current="page">
                                    <?= lang("Admin.{$uriSegments[$i]}") ?>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="<?= isIntegerString($uriSegments[$i]) ? 'is-active' : '' ?>">
                                <a href="<?= base_url($link) ?>">
                                    <?php
                                    // log_message('info', "Uri segment '$uriSegments[$i]' is integer: " . isIntegerString($uriSegments[$i])) 
                                    ?>
                                    <?= isIntegerString($uriSegments[$i]) ? lang("Admin.nox", ['x' => $uriSegments[$i]]) : lang("Admin.{$uriSegments[$i]}") ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>

                </ul>
            </nav>
        </div>
        <!-- Navbar burger for mobile -->
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <!-- <div id="navbarMen u" class="navbar-menu">
        <div class="navbar-start">
        </div>
        <div class="navbar-end">
        </div>
    </div> -->
</nav>