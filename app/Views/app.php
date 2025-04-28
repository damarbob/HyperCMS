<?php

use Config\Hyper;

$locale = service('request')->getLocale();
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? $title : config(Hyper::class)->appName ?></title>

    <!-- Styles & icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Style overrides -->
    <link rel="stylesheet" href="<?= base_url('assets/css/hyper-admin.css') ?>">
    <style>
        /* Gradient 1 */
        .gradient-1 {
            position: relative;
            overflow: hidden;
            z-index: 0;
        }

        .gradient-1::before {
            --size: 768px;
            --speed: 10s;
            --easing: cubic-bezier(0.8, 0.2, 0.2, 0.8);

            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: var(--size);
            height: var(--size);
            transform: translate(-50%, -50%);
            filter: blur(calc(var(--size) / 5));
            background-image: linear-gradient(var(--bulma-success), var(--bulma-primary));
            animation: rotate var(--speed) var(--easing) alternate infinite;
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            z-index: -1;
        }

        @keyframes rotate {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }

            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        @media (min-width: 720px) {
            .gradient-1::before {
                --size: 512px;
            }
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

<body style="overflow: hidden;">
    <!-- Main Content Section -->
    <section class="container is-fluid is-flex is-align-items-center is-justify-content-center is-flex-direction-column gradient-1" style="height: 100vh;">
        <h1 class="title has-text-centered is-size-1 has-text-primary-invert">
            <?= config(Hyper::class)->appName ?>
        </h1>
        <p class="subtitle has-text-primary-invert">
            <?= config(Hyper::class)->appVersion ?>
        </p>
    </section>

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

</body>

</html>