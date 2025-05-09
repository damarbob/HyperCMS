<?php

use Config\Hyper;
?>
<!DOCTYPE html>
<html>
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

    <!-- Style overrides -->
    <link rel="stylesheet" href="<?= base_url('assets/css/hyper-admin.css') ?>">
    <style>
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

                <?php if (ENVIRONMENT !== 'production'): ?>
                    console.log('Dark Mode Changed:', window.hyper_isDarkMode);
                <?php endif ?>
            }

            // Set initial value
            window.hyper_isDarkMode = darkModeMediaQuery.matches;

            <?php if (ENVIRONMENT !== 'production'): ?>
                console.log('Initial Dark Mode:', window.hyper_isDarkMode);
            <?php endif ?>

            // Listen for changes in the dark mode preference
            if (typeof darkModeMediaQuery.addEventListener === 'function') {
                darkModeMediaQuery.addEventListener('change', updateDarkModeStatus);
            } else if (typeof darkModeMediaQuery.addListener === 'function') {
                // Fallback for older browsers
                darkModeMediaQuery.addListener(updateDarkModeStatus);
            }
        } else {
            console.error('matchMedia is not supported in this browser.');
        }
    </script>

    <?= $this->renderSection('head') ?>
</head>

<body>
    <!-- Main Content Section -->
    <section class="section">
        <div class="container is-fluid">
            <?= $this->renderSection('content') ?>
        </div>
    </section>

    <?= $this->renderSection('footer') ?>

    <!-- Dependencies -->

    <!-- TinyMCE -->
    <script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>

    <!-- sweetalert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

</body>

</html>