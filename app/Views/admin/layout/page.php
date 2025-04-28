<?php

use Config\Hyper;

$locale = service('request')->getLocale();

$content = $this->renderSection('content');
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>">
<!-- data-theme="light" -->
<!-- data-theme="dark" -->

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? $title : (new Hyper)->appName ?></title>

    <!-- Styles & icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet"
        media="(prefers-color-scheme: dark)"
        href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

    <!-- Simplebar -->
    <script src="https://cdn.jsdelivr.net/npm/simplebar@6.3.0/dist/simplebar.min.js"></script>

    <!-- Style overrides -->
    <link rel="stylesheet" href="<?= base_url('assets/css/hyper-admin.css') ?>">
    <style>
        @font-face {
            font-family: 'codicon';
            src: url('https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.20.0/min/vs/base/browser/ui/codiconLabel/codicon/codicon.ttf') format('truetype');
        }

        :root {
            --hyper-sidebar-width: 16rem;
            --hyper-sidebar-collapsed-width: 4rem;
            --hyper-sidebar-width-mobile: 100vw;
            --hyper-navbar-height: 80px;
        }

        html,
        textarea {
            scrollbar-color: var(--bulma-primary-10) transparent;
            scrollbar-width: auto;
        }

        /* Overrides */
        /* Hide html overflow if modal is active */
        html:has(body .modal.is-active) {
            overflow-y: hidden;
        }

        .navbar {
            --bulma-navbar-background-color: hsla(var(--bulma-scheme-h), var(--bulma-scheme-s), var(--bulma-scheme-main-l), 0.5);
            backdrop-filter: blur(5px);
        }

        .modal {
            scrollbar-color: var(--bulma-primary-10) transparent;
            scrollbar-width: thin;
        }

        .modal-close {
            background: hsla(var(--bulma-scheme-h), var(--bulma-scheme-s), var(--bulma-delete-background-l), var(--bulma-delete-background-alpha));
        }

        .is-fullscreen {
            max-width: 100vw;
            max-height: 100vh;
            width: 100vw;
            height: 100vh;
        }

        .is-fullheight {
            max-height: 100vh;
            height: 100vh;
        }

        /* Transitions */
        .transition-width {
            transition: width 0.3s ease-out;
        }

        /* Sidebar */
        .sidebar {
            width: var(--hyper-sidebar-width);
            position: fixed;
            left: 0;
            /* To match Bulma columns margin (default to -12px) */
            top: calc(var(--bulma-column-gap)* -1);
            height: calc(100vh + var(--bulma-column-gap));
            background-color: hsla(var(--bulma-scheme-h), var(--bulma-scheme-s), var(--bulma-scheme-main-l), 0.5);
            backdrop-filter: blur(5px);
            box-shadow: var(--bulma-shadow);
            padding-inline-start: 32px;
            overflow: auto;
            /* Bulma navbar z-index + 2 (+1 for the overlay) */
            z-index: 32;
            scrollbar-color: var(--bulma-primary-10) transparent;
            scrollbar-width: thin;
        }

        .sidebar.is-collapsed {
            width: 0 !important;
            padding-inline: 0 !important;
        }

        .sidebar~.sidebar-overlay {
            width: 100vw;
            height: 100vh;
            background-color: hsla(var(--bulma-scheme-h), var(--bulma-scheme-s), var(--bulma-scheme-main-l), 0.5);
            bottom: 0;
            left: 0;
            position: fixed;
            right: 0;
            top: 0;
            /* Bulma navbar z-index + 1 */
            z-index: 31;
        }

        .sidebar.is-collapsed~.sidebar-overlay {
            display: none;
        }

        .sidebar .brand,
        .sidebar .brand-collapsed {
            white-space: nowrap;
            position: sticky;
            top: 0.75rem;
            background: linear-gradient(to bottom, transparent, var(--bulma-scheme-main) 40%, var(--bulma-scheme-main) 60%, transparent);
        }

        .sidebar .brand-collapsed {
            margin-bottom: 0.75rem;
        }

        .sidebar .brand-collapsed {
            display: none;
        }

        .sidebar .menu-list a {
            white-space: nowrap;
        }

        .sidebar.is-collapsed .brand-collapsed {
            display: block;
        }

        .sidebar.is-collapsed .brand {
            display: none !important;
        }

        .sidebar.is-collapsed .menu-label {
            display: none !important;
        }

        .sidebar.is-collapsed .menu-list a {
            text-align: center;
        }

        .sidebar.is-collapsed .menu-list li .text {
            display: none !important;
        }

        .sidebar.is-collapsed .menu-list li ul {
            display: none !important;
        }

        /* Mobile overrides */
        @media (max-width: 1023px) {

            /* Hide html overflow if sidebar is expanded */
            html:has(body .sidebar:not(.is-collapsed)) {
                overflow-y: hidden;
            }
        }

        /* Desktop overrides */
        @media (min-width: 1024px) {
            .sidebar {
                display: block;
            }

            .sidebar.is-collapsed {
                width: var(--hyper-sidebar-collapsed-width) !important;
                padding-inline: 0 !important;
            }

            .sidebar~.sidebar-overlay {
                display: none;
            }

            .sidebar~.content-wrapper {
                margin-left: var(--hyper-sidebar-width);
            }

            .sidebar.is-collapsed~.content-wrapper {
                margin-left: var(--hyper-sidebar-collapsed-width);
            }
        }

        /* End of sidebar */

        /* Hide nested submenus by default */
        .menu-list li ul {
            display: none;
        }

        /* When the parent li has the is-active class, display its submenu */
        .menu-list li.is-active ul {
            display: block;
        }
    </style>

    <!-- Javascript for dark mode detection -->
    <script>
        // Ensure matchMedia is supported by the browser
        if (window.matchMedia) {
            // Create media query list for dark mode
            const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

            // Function to update the window property
            function updateDarkModeStatus(e) {
                window.hyper_isDarkMode = e.matches;
                console.log('Dark Mode Changed:', window.hyper_isDarkMode);
            }

            // Set initial value
            window.hyper_isDarkMode = darkModeMediaQuery.matches;
            <?php if (ENVIRONMENT !== 'production'): ?>
                console.log('Initial Dark Mode:', window.hyper_isDarkMode);
            <?php endif; ?>

            // Listen for changes in the dark mode preference
            if (typeof darkModeMediaQuery.addEventListener === 'function') {
                darkModeMediaQuery.addEventListener('change', updateDarkModeStatus);
            } else if (typeof darkModeMediaQuery.addListener === 'function') {
                // Fallback for older browsers
                darkModeMediaQuery.addListener(updateDarkModeStatus);
            }
        } else {
            console.log('matchMedia is not supported in this browser.');
        }
    </script>

    <?= $this->renderSection('head') ?>
</head>

<body>
    <!-- Main Content Section -->
    <section class="container is-fluid">
        <?= $this->include("admin/layout/sidebar") ?>
        <!-- Main Content Column -->
        <div class="content-wrapper">
            <?= $this->include("admin/layout/navbar") ?>
            <?= $this->renderSection('contentNoWrapper') ?>
            <?php if (!empty($content)): ?>
                <div class="column">
                    <?= $content ?>
                </div>
            <?php endif ?>
        </div>
    </section>

    <!-- Hyper CMS bootstrap JS -->
    <script type="module" src="<?= base_url('assets/js/main.js') ?>"></script>

    <?= $this->renderSection('scripts') ?>

    <!-- Iframe check -->
    <script type="text/javascript">
        const inIframe = window.self !== window.top; // Check if loaded inside an iframe

        // Adjust UI to disable sidebar if in iframe
        if (inIframe) {
            document.querySelector('.navbar').classList.add('is-hidden');
            document.querySelector('.sidebar').classList.add('is-hidden');
        }

        // Hide elements that are iframe-specific with is-in-iframe class
        if (!inIframe) {
            document.querySelectorAll('.is-in-iframe').forEach(element => {
                if (!element.classList.contains('is-hidden')) {
                    element.classList.add('is-hidden');
                }
            })
        } else {
            document.querySelectorAll('.is-in-iframe').forEach(element => {
                if (element.classList.contains('is-hidden')) {
                    element.classList.remove('is-hidden');
                }
            })
        }
    </script>

    <!-- Dependencies -->

    <!-- Tippy.js -->
    <?php if (ENVIRONMENT !== 'production'): ?>
        <script src="https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"></script>
        <script src="https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"></script>
    <?php else: ?>
        <script src="https://unpkg.com/@popperjs/core@2"></script>
        <script src="https://unpkg.com/tippy.js@6"></script>
    <?php endif; ?>
    <script type="text/javascript">
        tippy('[data-tippy-content]');
    </script>

    <!-- TinyMCE -->
    <script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>

    <!-- sweetalert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- @TODO: sweetalert2 custom styling -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/themes@5.0.27/bulma/bulma.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script> -->

    <?php if (session()->getFlashdata('success')) : ?>
        <!-- JavaScript for displaying success/error notifications -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.hyper_swal.success("<?= lang("Admin.success") ?>", {
                    text: "<?= session()->getFlashdata('success') ?>"
                });
            });
        </script>
    <?php elseif (session()->getFlashdata('error')) : ?>
        <!-- JavaScript for displaying success/error notifications -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.hyper_swal.error("<?= lang("Admin.error") ?>", {
                    text: "<?= session()->getFlashdata('error') ?>",
                    confirmButtonColor: "var(--bulma-primary)",
                    showConfirmButton: true,
                    timer: false,
                });
            });
        </script>
    <?php endif; ?>

    <script>
        // Functions to open and close a modal
        function openModal($el) {
            $el.classList.add('is-active');
        }

        function closeModal($el) {
            $el.classList.remove('is-active');
        }

        function toggleActive($el) {
            $el.classList.toggle('is-active');
        }

        function toggleCollapse($el) {
            $el.classList.toggle('is-collapsed');
        }

        function closeAllModals() {
            (document.querySelectorAll('.modal') || []).forEach(($modal) => {
                closeModal($modal);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Add a click event on buttons to open a specific modal
            (document.querySelectorAll('.js-modal-trigger') || []).forEach(($trigger) => {
                const modal = $trigger.dataset.target;
                const $target = document.getElementById(modal);

                $trigger.addEventListener('click', () => {
                    openModal($target);
                });
            });

            // Add a click event on various child elements to close the parent modal
            (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete, .modal-card-foot .button') || []).forEach(($close) => {
                const $target = $close.closest('.modal');

                $close.addEventListener('click', () => {
                    closeModal($target);
                });
            });

            // Add a keyboard event to close all modals
            document.addEventListener('keydown', (event) => {
                if (event.key === "Escape") {
                    closeAllModals();
                }
            });
        });
    </script>

    <!-- JavaScript for toggling states -->
    <script>
        const navbarBurgers = Array.from(document.querySelectorAll('.navbar-burger'));
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const submenuLinks = document.querySelectorAll('.menu-list li > a.has-submenu');

        // 0. Load saved sidebar state from localStorage (if available)
        if (sidebar && window.innerWidth >= 1024) {
            const collapsedState = localStorage.getItem('sidebar-collapsed');
            // If true, add the is-collapsed class, otherwise remove it.
            if (collapsedState === 'true') {
                sidebar.classList.add('is-collapsed');
            } else {
                sidebar.classList.remove('is-collapsed');
            }
            setTimeout(() => {
                sidebar.classList.add('transition-width');
            }, 1); // Add transition after initial load
        }

        document.addEventListener('DOMContentLoaded', () => {

            // 1. Navbar burger toggle (for mobile devices)
            if (navbarBurgers.length > 0) {
                navbarBurgers.forEach(burger => {
                    burger.addEventListener('click', () => {
                        const targetId = burger.dataset.target;
                        const targetElem = document.getElementById(targetId);
                        burger.classList.toggle('is-active');
                        targetElem.classList.toggle('is-active');
                    });
                });
            }

            // 2. Sidebar toggle button - save state on click
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('is-collapsed');
                    // Determine the current state and save it in localStorage
                    const isCollapsed = sidebar.classList.contains('is-collapsed');
                    localStorage.setItem('sidebar-collapsed', isCollapsed);
                });
            }

            // 3. Submenu toggle: When a link with class "has-submenu" is clicked,
            // toggle the "is-active" state of its parent list item.
            submenuLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (sidebar.classList.contains('is-collapsed')) {
                        return;
                    }
                    e.preventDefault(); // Prevent the default hyperlink behavior
                    link.classList.toggle('is-active');
                    link.parentElement.classList.toggle('is-active');
                });
            });
        });
    </script>

    <!-- Clamp.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Clamp.js/0.5.1/clamp.min.js" integrity="sha512-9PanvIYgF2gT2Yau/uKb9ms+cOBNVo+sQzWDb+nLX5F4FZvEUiuFhKIQPmVU2jCvZKKTVB3Y8giDezNt/1H3xg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Wait for the DOM to load
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize Flatpickr on all datetime-local inputs automatically
            flatpickr("input[type='datetime-local']", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                // Optionally, add any additional Flatpickr options here
            });
        });
    </script>

</body>

</html>