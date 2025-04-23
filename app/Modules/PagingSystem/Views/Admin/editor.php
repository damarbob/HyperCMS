<?= $this->extend('admin/layout/page_blank') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/css/grapes.min.css" integrity="sha256-1I3el0pvNWfTUd1SEXRESKNhHSpnELFugVLiTDt//cY=" crossorigin="anonymous">

<!-- Add gradient  -->
<link href="https://unpkg.com/grapick/dist/grapick.min.css" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap');
    /* @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap'); */
    /* @import url('https://fonts.googleapis.com/css2?family=Boldonse&display=swap'); */

    :root {
        --gjs-main-color: var(--bulma-scheme-main);
        --gjs-primary-color: var(--bulma-scheme-main);
        --gjs-secondary-color: var(--bulma-text);
        --gjs-tertiary-color: var(--bulma-primary-soft);
        --gjs-quaternary-color: var(--bulma-primary);
        --gjs-font-color: var(--bulma-text);
        --gjs-font-color-active: var(--bulma-text);
        --gjs-dark-text-shadow: hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-l), 0.5);
        --gjs-main-font: "Poppins", system-ui;
        /* --gjs-font-size: var(--bulma-body-font-size); */

        /* --gjs-main-color: #444444; */
        /* --gjs-primary-color: #444444; */
        /* --gjs-secondary-color: #dddddd; */
        /* --gjs-tertiary-color: #804f7b; */
        /* --gjs-quaternary-color: #d278c9; */
        /* --gjs-font-color: #dddddd; */
        /* --gjs-font-color-active: #f8f8f8; */
        --gjs-main-dark-color: rgba(0, 0, 0, 0.2);
        --gjs-secondary-dark-color: rgba(0, 0, 0, 0.1);
        --gjs-main-light-color: rgba(255, 255, 255, 0.1);
        --gjs-secondary-light-color: rgba(255, 255, 255, 0.7);
        --gjs-soft-light-color: rgba(255, 255, 255, 0.015);
        --gjs-color-blue: #3b97e3;
        --gjs-color-red: #dd3636;
        --gjs-color-yellow: #ffca6f;
        --gjs-color-green: #62c462;
        --gjs-left-width: 15%;
        --gjs-color-highlight: #71b7f1;
        --gjs-color-warn: #ffca6f;
        --gjs-handle-margin: -5px;
        --gjs-light-border: rgba(255, 255, 255, 0.05);
        --gjs-arrow-color: rgba(255, 255, 255, 0.7);
        /* --gjs-dark-text-shadow: rgba(0, 0, 0, 0.2); */
        --gjs-color-input-padding: 22px;
        --gjs-input-padding: 5px;
        --gjs-padding-elem-classmanager: 5px 6px;
        --gjs-upload-padding: 150px 10px;
        --gjs-animation-duration: 0.2s;
        /* --gjs-main-font: Helvetica, sans-serif; */
        --gjs-font-size: 0.75rem;
        --gjs-placeholder-background-color: var(--gjs-color-green);
        --gjs-canvas-top: 40px;
        --gjs-flex-item-gap: 5px;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<textarea id="editor_example" class="hyper-editor"></textarea>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('\Modules\PagingSystem\Views\Admin\editor_scripts') ?>
<?= $this->endSection() ?>