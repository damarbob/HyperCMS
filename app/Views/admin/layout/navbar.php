<?php

use Config\Hyper;

helper('hyper_type_checking');
?>
<!-- Navbar -->
<nav class="navbar has-shadow py-3" role="navigation" aria-label="navbar" style="position: sticky; top: 0;">
    <div class="navbar-brand">
        <div class="navbar-item">
            <!-- Sidebar toggle button -->
            <div class="buttons">
                <button id="sidebarToggle" class="button">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
        <div class="navbar-item">
            <h1 class="navbar-item">
                <strong><?= $title ?></strong>
            </h1>
            <?php if (ENVIRONMENT !== 'production'): ?>
                <nav class="breadcrumb has-succeeds-separator is-hidden-touch" aria-label="breadcrumbs">
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
                                        <?= isset($title) ? $title : lang("Admin." . lcfirst(str_replace('-', '', ucwords($uriSegments[$i], '-')))) ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="<?= isIntegerString($uriSegments[$i]) ? 'is-active' : '' ?>">
                                    <a href="<?= base_url($link) ?>">
                                        <?php
                                        // log_message('info', "Uri segment '$uriSegments[$i]' is integer: " . isIntegerString($uriSegments[$i])) 
                                        ?>
                                        <?= isIntegerString($uriSegments[$i]) ? lang("Admin.nox", ['x' => $uriSegments[$i]]) : lang("Admin." . lcfirst(str_replace('-', '', ucwords($uriSegments[$i], '-')))) ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>

                    </ul>
                </nav>
            <?php endif; ?>
        </div>
        <!-- Navbar burger for mobile -->
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <div id="navbarMenu" class="navbar-menu">
        <div class="navbar-start">
        </div>
        <div class="navbar-end">
            <div class="navbar-item has-dropdown" onclick="toggleNavbarDropdown(this, this.querySelector('.navbar-link'), this.querySelector('.navbar-dropdown'))">
                <a class="navbar-link">
                    <div class="is-flex is-flex-direction-row is-align-items-center" style="gap: var(--bulma-column-gap);">
                        <!-- Profile image -->
                        <span class="icon">
                            <img id="profileImage" class="img-profile rounded-circle" src="" alt="Profile Image">
                        </span>
                        <span><?= (auth()->user()->username) ?></span>
                    </div>
                </a>

                <div class="navbar-dropdown">
                    <a href="<?= base_url('admin/profile') ?>" class="navbar-item">
                        <?= lang('Admin.profile') ?>
                    </a>
                    <hr class="navbar-divider">
                    <a href="<?= base_url('auth/logout') ?>" class="navbar-item">
                        <?= lang('Admin.logout') ?>
                    </a>
                    <hr class="navbar-divider">
                    <div class="navbar-item">
                        <?= (new Hyper)->appName ?> v<?= (new Hyper)->appVersion ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<?= $this->section('footer') ?>
<!-- Include TinyColor2 from a CDN -->
<script src="https://cdn.jsdelivr.net/npm/tinycolor2@1.6.0/cjs/tinycolor.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@floating-ui/core@1.6.9"></script>
<script src="https://cdn.jsdelivr.net/npm/@floating-ui/dom@1.6.13"></script>
<!-- JavaScript -->
<script>
    // Utility function that removes "deg" from the color string.
    function removeDegUnits(colorString) {
        return colorString.replace(/(\d+)\s*deg/gi, '$1');
    }

    // Retrieve a CSS color value (which might be in HSL, HSLA, etc.)
    const primaryColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--bulma-primary').trim();

    // Convert to hex using TinyColor2 (this returns a string starting with "#")
    const primaryHex = tinycolor(removeDegUnits(primaryColor)).toHexString().substring(1); // remove the '#'

    const bodyBgColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--bulma-scheme-main').trim();
    const bodyBgHex = tinycolor(removeDegUnits(bodyBgColor)).toHexString().substring(1);

    // Now create your URL using these hex values
    const username = '<?= urlencode(auth()->user()->username) ?>';
    const imgUrl = `https://ui-avatars.com/api/?size=32&name=${username}&rounded=true&background=${primaryHex}&color=${bodyBgHex}&bold=true`;

    document.getElementById('profileImage').src = imgUrl;
</script>
<script>
    function toggleNavbarDropdown(el, referenceEl, dropdownEl) {
        toggleActive(el);
        updateDropdownPosition(referenceEl, dropdownEl);
    }

    function updateDropdownPosition(referenceEl, dropdownEl) {
        FloatingUIDOM.computePosition(referenceEl, dropdownEl, {
            placement: 'bottom-end',
            middleware: [
                FloatingUIDOM.offset(5), // add a small offset from the reference element
                FloatingUIDOM.shift() // keep it within the viewport if possible
            ]
        }).then(({
            x,
            y
        }) => {
            Object.assign(dropdownEl.style, {
                left: `${x}px`,
                top: `${y}px`
            });
        });
    }
</script>
<?= $this->endSection() ?>