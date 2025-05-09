<?php

use Config\Hyper;

$locale = service('request')->getLocale();

$content = $this->renderSection('content');
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>" class="not-loaded">
<!-- data-theme="light" -->
<!-- data-theme="dark" -->

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? $title : config(Hyper::class)->appName ?></title>

    <!-- Styles & icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet"
        media="(prefers-color-scheme: dark)"
        href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

    <!-- Style overrides -->
    <link rel="stylesheet" href="<?= base_url('assets/css/hyper-admin.css') ?>">
    <style>
        @font-face {
            font-family: 'codicon';
            src: url('https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.20.0/min/vs/base/browser/ui/codiconLabel/codicon/codicon.ttf') format('truetype');
        }

        /* CUSTOMIZATION */

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

        /* Disable all pointer interactions (clicks, hovers, etc.) when not loaded */
        .not-loaded * {
            pointer-events: none;
        }

        /* Hide iframe-only element */
        body .is-in-iframe {
            display: none;
        }

        /* Show iframe-only element on iframe */
        body.is-in-iframe .is-in-iframe {
            display: initial;
        }

        /* Hide sidebar and navbar on iframe */
        body.is-in-iframe .sidebar,
        body.is-in-iframe .navbar {
            display: none;
        }

        /* Maximize size to the viewport */
        .is-fullscreen {
            max-width: 100vw;
            max-height: 100vh;
            width: 100vw;
            height: 100vh;
        }

        /* Maxizime height to the viewport height */
        .is-fullheight {
            max-height: 100vh;
            height: 100vh;
        }

        /* END OF CUSTOMIZATION */

        /* TRANSITIONS */

        /* Width */
        .transition-width {
            transition: width 0.3s ease-out;
        }

        /* OVERRIDES */

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

        /* Hide nested submenus by default */
        .menu-list li ul {
            display: none;
        }

        /* When the parent li has the is-active class, display its submenu */
        .menu-list li.is-active ul {
            display: block;
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

        .sidebar:not(.is-active) {
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

        .sidebar:not(.is-active)~.sidebar-overlay {
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

        .sidebar:not(.is-active) .brand-collapsed {
            display: block;
        }

        .sidebar:not(.is-active) .brand {
            display: none !important;
        }

        .sidebar:not(.is-active) .menu-label {
            display: none !important;
        }

        .sidebar:not(.is-active) .menu-list a {
            text-align: center;
        }

        .sidebar:not(.is-active) .menu-list li .text {
            display: none !important;
        }

        .sidebar:not(.is-active) .menu-list li ul {
            display: none !important;
        }

        /* Mobile overrides */
        @media (max-width: 1023px) {

            /* Hide html overflow if sidebar is expanded */
            html:has(body .sidebar.is-active) {
                overflow-y: hidden;
            }
        }

        /* Desktop overrides */
        @media (min-width: 1024px) {
            .sidebar {
                display: block;
            }

            .sidebar:not(.is-active) {
                width: var(--hyper-sidebar-collapsed-width) !important;
                padding-inline: 0 !important;
            }

            .sidebar~.sidebar-overlay {
                display: none;
            }

            .sidebar~.content-wrapper {
                margin-left: var(--hyper-sidebar-width);
            }

            .sidebar:not(.is-active)~.content-wrapper {
                margin-left: var(--hyper-sidebar-collapsed-width);
            }
        }

        /* End of sidebar */

        /* SweetAlert2 */

        body.swal2-no-backdrop .swal2-container .swal2-modal.swal2-show,
        body.swal2-no-backdrop .swal2-container .swal2-toast.swal2-show {
            box-shadow: var(--bulma-shadow);
        }

        div:where(.swal2-container) div:where(.swal2-popup) {
            --swal2-background: var(--bulma-scheme-main);
        }

        div:where(.swal2-container) div:where(.swal2-actions) button:where(.swal2-styled) {
            color: var(--bulma-scheme-main);
        }

        div:where(.swal2-container) div:where(.swal2-actions) button:where(.swal2-styled):where(.swal2-cancel),
        div:where(.swal2-container) div:where(.swal2-actions) button:where(.swal2-styled):where(.swal2-confirm),
        div:where(.swal2-container) div:where(.swal2-actions) button:where(.swal2-styled):where(.swal2-close) {
            border-radius: var(--bulma-radius);
        }

        /* End of SweetAlert2 */

        /* END OF OVERRIDES */
    </style>

    <!-- JavaScript for Dark Mode Detection -->
    <script>
        /**
         * This script detects the user's dark mode preference using the browser's
         * matchMedia API and updates a global flag (window.hyper_isDarkMode) accordingly.
         * It also listens for any changes to the preference.
         */

        // Ensure the browser supports matchMedia
        if (typeof window.matchMedia === 'function') {
            // Create a MediaQueryList object for the dark mode media query.
            const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

            /**
             * Update the global dark mode status.
             *
             * @param {MediaQueryListEvent|MediaQueryList} e - The event or object 
             *   containing the current match results.
             */
            const updateDarkModeStatus = (e) => {
                window.hyper_isDarkMode = e.matches;

                <?php if (ENVIRONMENT !== 'production'): ?>
                    console.log('Dark Mode Changed:', window.hyper_isDarkMode);
                <?php endif ?>
            };

            // Set the global variable based on the initial media query state.
            window.hyper_isDarkMode = darkModeMediaQuery.matches;

            // In non-production environments, log the initial dark mode status for debugging.
            <?php if (ENVIRONMENT !== 'production'): ?>
                console.log('Initial Dark Mode:', window.hyper_isDarkMode);
            <?php endif; ?>

            // Listen for changes to the user's dark mode preference.
            // Modern browsers support addEventListener on MediaQueryList objects.
            if (typeof darkModeMediaQuery.addEventListener === 'function') {
                darkModeMediaQuery.addEventListener('change', updateDarkModeStatus);
            }
            // Fallback for older browsers using the deprecated addListener.
            else if (typeof darkModeMediaQuery.addListener === 'function') {
                darkModeMediaQuery.addListener(updateDarkModeStatus);
            }
        } else {
            console.error('matchMedia is not supported in this browser.');
        }
    </script>

    <!-- Iframe check -->
    <script type="text/javascript">
        window.hyper_inIframe = window.self !== window.top; // Check if loaded inside an iframe
    </script>

    <?= $this->renderSection('head') ?>
</head>

<body>
    <script>
        // Add is-in-iframe class to the body
        if (window.hyper_inIframe) document.body.classList.add('is-in-iframe');
    </script>

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

    <?= $this->renderSection('footer') ?>

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
        // -------------------------------
        // Initialize Tippy.js for tooltips
        // -------------------------------

        // Initialize Tippy.js here
        // to prevent crashing with other tippy initialization
        // from other page
        tippy('[data-tippy-content]');
    </script>

    <!-- TinyMCE -->
    <script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>

    <!-- sweetalert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Clamp.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Clamp.js/0.5.1/clamp.min.js" integrity="sha512-9PanvIYgF2gT2Yau/uKb9ms+cOBNVo+sQzWDb+nLX5F4FZvEUiuFhKIQPmVU2jCvZKKTVB3Y8giDezNt/1H3xg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Hyper CMS bootstrap JS -->
    <script type="module" src="<?= base_url('assets/js/main.js') ?>"></script>

    <!-- JavaScript for displaying success/error notifications -->
    <script>
        // Wait until the DOM has fully loaded before running our notification logic.
        document.addEventListener('DOMContentLoaded', () => {

            // ----------------------------------------------------
            // Success Notification
            // ----------------------------------------------------
            // If a success message exists in the session flash data,
            // use the global hyper_swal wrapper to display a success toast.
            // The title is localized using lang("Admin.success").
            <?php if (session()->getFlashdata('success')) : ?>
                window.hyper_swal.success("<?= lang("Admin.success") ?>", {
                    text: "<?= session()->getFlashdata('success') ?>" // Notification detail text
                });
            <?php endif; ?>

            // ----------------------------------------------------
            // Error Notification
            // ----------------------------------------------------
            // If no success message is set but an error message exists,
            // display an error notification using the hyper_swal error method.
            // The configuration customizes the appearance by overriding the confirm button color,
            // ensuring the confirm button is displayed and disabling the timer.
            <?php if (session()->getFlashdata('error')) : ?>

                window.hyper_swal.error("<?= lang("Admin.error") ?>", {
                    text: "<?= session()->getFlashdata('error') ?>",
                    confirmButtonColor: "var(--bulma-primary)",
                    showConfirmButton: true,
                    timer: false,
                });
            <?php endif; ?>

        });
    </script>


    <script>
        // ==========================================================
        // Global Element Selections and Utility Functions
        // ==========================================================

        // Select elements for the navbar (burger menu), sidebar toggle, sidebar, and submenu links.
        const navbarBurgers = Array.from(document.querySelectorAll('.navbar-burger'));
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const submenuLinks = document.querySelectorAll('.menu-list li > a.has-submenu');

        /**
         * Opens a modal by adding the is-active class.
         * @param {HTMLElement} $el - The modal element to open.
         */
        function openModal($el) {
            $el.classList.add('is-active');
        }

        /**
         * Closes a modal by removing the is-active class.
         * @param {HTMLElement} $el - The modal element to close.
         */
        function closeModal($el) {
            $el.classList.remove('is-active');
        }

        /**
         * Toggles the is-active state on an element.
         * @param {HTMLElement} $el - The element to toggle.
         */
        function toggleActive($el) {
            $el.classList.toggle('is-active');
        }

        /**
         * Toggles the is-collapsed state on an element.
         * @param {HTMLElement} $el - The element to toggle.
         */
        function toggleCollapse($el) {
            $el.classList.toggle('is-collapsed');
        }

        /**
         * Closes all modals on the page.
         */
        function closeAllModals() {
            (document.querySelectorAll('.modal') || []).forEach(($modal) => {
                closeModal($modal);
            });
        }

        // ==========================================================
        // Sidebar Setup: Load Saved State and Apply Transition
        // ==========================================================

        // When viewing on desktop screens, check localStorage for the sidebar active state.
        if (sidebar && window.innerWidth >= 1024) {
            const collapsedState = localStorage.getItem('sidebar-active');
            if (collapsedState === 'true') {
                sidebar.classList.remove('is-active');
            } else {
                sidebar.classList.add('is-active');
            }
            // Add a transition class after the initial load to animate future width changes.
            setTimeout(() => {
                sidebar.classList.add('transition-width');
            }, 1); // A short delay to apply the transition after the initial layout.
        }

        // ==========================================================
        // DOMContentLoaded: Set Up Event Listeners After DOM Parsing
        // ==========================================================

        document.addEventListener('DOMContentLoaded', () => {
            // -------------------------------
            // Modal Triggers & Closes
            // -------------------------------

            // Open modals: Attach a click event for every element with the .js-modal-trigger class.
            (document.querySelectorAll('.js-modal-trigger') || []).forEach(($trigger) => {
                const modal = $trigger.dataset.target;
                const $target = document.getElementById(modal);

                $trigger.addEventListener('click', () => {
                    openModal($target);
                });
            });

            // Close modals: Attach click events to elements that should close the modal.
            (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete, .modal-card-foot .button') || []).forEach(($close) => {
                const $target = $close.closest('.modal');

                $close.addEventListener('click', () => {
                    closeModal($target);
                });
            });

            // Close all modals on Escape key press.
            document.addEventListener('keydown', (event) => {
                if (event.key === "Escape") {
                    closeAllModals();
                }
            });

            // -------------------------------
            // Navbar Burger Toggle (Mobile)
            // -------------------------------

            // When a navbar burger icon is clicked, toggle its active state and the target menu.
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

            // -------------------------------
            // Sidebar Toggle Button
            // -------------------------------

            // When the sidebar toggle button is clicked, collapse or expand the sidebar,
            // then save the current state in localStorage.
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('is-active');
                    const isCollapsed = !sidebar.classList.contains('is-active');
                    localStorage.setItem('sidebar-active', isCollapsed);
                });
            }

            // -------------------------------
            // Submenu Toggle in Sidebar Menu
            // -------------------------------

            // When a submenu link is clicked, if the sidebar is active,
            // prevent the default link action and toggle the active state on the link and its parent.
            submenuLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (!sidebar.classList.contains('is-active')) {
                        return;
                    }
                    e.preventDefault();
                    link.classList.toggle('is-active');
                    link.parentElement.classList.toggle('is-active');
                });
            });
        });
    </script>

    <script>
        // ==========================================================
        // Third-party libraries initialization
        // ==========================================================

        document.addEventListener('DOMContentLoaded', () => {

            // -------------------------------
            // Initialize Flatpickr for Date/Time Inputs
            // -------------------------------

            // Automatically initialize Flatpickr on all inputs of type="datetime-local".
            flatpickr("input[type='datetime-local']", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                // Additional Flatpickr options can be added here.
            });
        });
    </script>

    <script>
        // Wait until the full page has loaded, then remove the "not-loaded" class
        document.addEventListener('DOMContentLoaded', () => {
            document.documentElement.classList.remove('not-loaded');

            <?php if (ENVIRONMENT !== 'production'): ?>
                console.log('Page is fully loaded, interactivity enabled.');
            <?php endif ?>
        });
    </script>

</body>

</html>