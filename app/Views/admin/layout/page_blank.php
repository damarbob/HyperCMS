<!DOCTYPE html>
<html>
<!-- data-theme="light" -->
<!-- data-theme="dark" -->

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?></title>

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

        /* Optional: Make the sidebar sticky as you scroll */
        .sidebar {
            position: sticky;
            top: 1rem;
        }

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
                window.isDarkMode = e.matches;
                console.log('Dark Mode Changed:', window.isDarkMode);
            }

            // Set initial value
            window.isDarkMode = darkModeMediaQuery.matches;
            console.log('Initial Dark Mode:', window.isDarkMode);

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
    <?= $this->renderSection('content') ?>

    <?= $this->renderSection('scripts') ?>

    <!-- Dependencies -->

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
                Swal.fire({
                    title: "<?= lang("Admin.success") ?>",
                    text: "<?= session()->getFlashdata('success') ?>",
                    icon: 'success',
                    showConfirmButton: false,
                    confirmButtonColor: "var(--bulma-primary)",
                    backdrop: false,
                    position: 'top-end',
                    theme: window.isDarkMode ? 'dark' : 'light',
                    toast: true,
                    timer: 3000,
                    timerProgressBar: true,
                });
            });
        </script>
    <?php elseif (session()->getFlashdata('error')) : ?>
        <!-- JavaScript for displaying success/error notifications -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    title: "<?= lang("Admin.error") ?>",
                    text: "<?= session()->getFlashdata('error') ?>",
                    icon: 'error',
                    // showConfirmButton: false,
                    confirmButtonColor: "var(--bulma-primary)",
                    backdrop: false,
                    position: 'top-end',
                    theme: window.isDarkMode ? 'dark' : 'light',
                    toast: true,
                    // timer: 5000,
                    // timerProgressBar: true,
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
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Navbar burger toggle (for mobile devices)
            const navbarBurgers = Array.from(document.querySelectorAll('.navbar-burger'));
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

            // 2. Sidebar toggle button
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('is-hidden');
                });
            }

            // 3. Submenu toggle: When a link with class "has-submenu" is clicked,
            // toggle the "is-active" state of its parent list item.
            const submenuLinks = document.querySelectorAll('.menu-list li > a.has-submenu');
            submenuLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevent the default hyperlink behavior
                    link.parentElement.classList.toggle('is-active');
                });
            });
        });
    </script>

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