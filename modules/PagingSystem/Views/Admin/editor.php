<?= $this->extend('admin/layout/page_blank') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/css/grapes.min.css" integrity="sha256-1I3el0pvNWfTUd1SEXRESKNhHSpnELFugVLiTDt//cY=" crossorigin="anonymous">
<!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.8/dist/css/grapes.min.css" integrity="sha256-Ht0gb7nkHGDXDGbP2y554rk1jfXJUjM6i1pqWYn4wtQ=" crossorigin="anonymous"> -->

<!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs-component-code-editor@1.0.20/dist/grapesjs-component-code-editor.min.css" integrity="sha256-gH8DRO4UN0+fRV5Tb1zLFndmh9dGJcr263onBBI+y1w=" crossorigin="anonymous"> -->
<!-- <link href="https://unpkg.com/grapesjs-code-editor/dist/style.css" rel="stylesheet"> -->

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
        /* --gjs-main-dark-color: var(--bulma-scheme-main); */
        --gjs-main-dark-color: hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-l), 0.1);
        --gjs-secondary-dark-color: var(--bulma-scheme-main);
        /* --gjs-secondary-dark-color: hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-l), 0.1); */
        /* --gjs-main-light-color: rgba(255, 255, 255, 0.1); */
        --gjs-main-light-color: var(--bulma-primary-soft);
        /* --gjs-secondary-light-color: rgba(255, 255, 255, 0.7); */
        --gjs-secondary-light-color: var(--bulma-primary-bold);
        --gjs-soft-light-color: rgba(255, 255, 255, 0.015);
        --gjs-color-blue: #3b97e3;
        --gjs-color-red: #dd3636;
        --gjs-color-yellow: #ffca6f;
        --gjs-color-green: #62c462;
        --gjs-left-width: 0%;
        --gjs-color-highlight: #71b7f1;
        --gjs-color-warn: #ffca6f;
        --gjs-handle-margin: -5px;
        --gjs-light-border: rgba(255, 255, 255, 0.05);
        --gjs-arrow-color: var(--gjs-secondary-color);
        /* --gjs-dark-text-shadow: rgba(0, 0, 0, 0.2); */
        --gjs-color-input-padding: 22px;
        --gjs-input-padding: 5px;
        --gjs-padding-elem-classmanager: 5px 6px;
        --gjs-upload-padding: 150px 10px;
        --gjs-animation-duration: 0.2s;
        /* --gjs-main-font: Helvetica, sans-serif; */
        --gjs-font-size: 0.75rem;
        --gjs-placeholder-background-color: var(--gjs-color-green);
        --gjs-canvas-top: 41.6px;
        --gjs-flex-item-gap: 5px;
    }

    /* Main Content Area */
    .main-section {
        display: flex;
        flex: 1;
        min-height: 0;
        justify-content: flex-start;
        align-items: stretch;
        flex-wrap: nowrap;
    }

    /* Left Panel */
    .left-panel {
        /* overflow-y: auto; */
        position: relative;
        z-index: 10;
        background: var(--gjs-primary-color);
        height: 100vh;
    }

    .left-panel-container {
        display: flex;
    }

    .left-panel-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 4px;
        background: var(--gjs-primary-color);
    }

    .left-panel-content {
        display: flex;
        flex-direction: column;
        width: 250px;
        /* border-right: 1px solid #ddd; */
        /* overflow-y: auto; */
        height: 100vh;
    }

    .left-panel-content-header {
        padding: 6.4px;
        font-size: 1.2rem;
        color: var(--gjs-font-color);
        background: var(--gjs-primary-color);
        font-weight: bold;
    }

    .left-panel-content-pane {
        overflow: auto;
        flex-grow: 2;
        background: var(--gjs-primary-color) !important;
    }

    /* Canvas Area */
    .canvas-container {
        flex-grow: 1;
        /* position: relative; */
    }

    .top-panel {
        padding: 0;
        width: 100%;
        /* display: flex; */
        position: initial;
        justify-content: center;
        justify-content: space-between;
        background: var(--gjs-primary-color);
        height: 41.6px;
    }

    .top-panel-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .devices-panel {
        position: inherit;
    }

    .options-panel {
        position: initial;
    }

    /* Right Panel */
    .right-panel {
        /* width: 300px;
        border-left: 1px solid #ddd;
        background: #fff; */
        /* overflow-y: auto; */
        flex-basis: 230px;
        position: relative;
        z-index: 10;
        background: var(--gjs-primary-color);
        height: max-content;
    }

    .right-panel-container {
        overflow: auto;
        height: 100vh;
    }

    /* .gjs-resizer-h {} */

    .right-panel-tabs {
        display: flex;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 50;
        background: var(--gjs-primary-color);
        /* gap: 15px;
        padding-right: 20px; */
    }

    /* .tab-button {
        flex: 1;
        text-align: center;
    } */

    .right-panel-content {
        overflow-y: auto;
        /* height: calc(100vh - 40px); */

    }

    #no-select-state {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        padding: 20px;
        color: var(--gjs-font-color);
        background: var(--gjs-primary-color);
    }

    .attributes-panel {
        background: var(--gjs-primary-color);
        /* border-left: 1px solid var(--gjs-light-border); */
        padding: 20px;
        /* overflow-y: auto; */
        display: flex;
        flex-direction: column;
    }

    .panel-header {
        /* padding-bottom: 15px; */
        border-bottom: 1px solid var(--gjs-light-border);
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-header h2 {
        font-size: 18px;
        /* color: var(--dark); */
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .no-component {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--gjs-font-color);
    }

    .no-component i {
        font-size: 48px;
        margin-bottom: 15px;
        color: var(--bulma-primary-on-scheme);
    }

    .component-info {
        background: var(--gjs-primary-color);
        border-radius: 8px;
        padding-top: 8px;
        padding-bottom: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-l), 0.2);
        /* border: 1px solid var(--gjs-light-border); */
    }

    .component-type {
        font-size: 14px;
        font-weight: 600;
        text-align: center;
        gap: 8px;
    }

    .section-title {
        font-size: 14px;
        font-weight: 600;
        /* color: var(--dark); */
        margin: 20px 0 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .add-attribute {
        color: var(--gjs-font-color);
        background: var(--gjs-primary-color);
        border: none;
        border-radius: 4px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.3s;
    }

    .add-attribute:hover {
        background: var(--gjs-tertiary-color);
    }

    .attributes-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .attribute-row {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: center;
    }

    .remove-attribute {
        /* background: var(--gjs-color-red); */
        color: var(--gjs-font-color);
        border: none;
        border-radius: 4px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.3s;
    }

    .remove-attribute:hover {
        background: var(--gjs-color-red);
    }

    .code-preview {
        background: var(--gjs-primary-color);
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
        color: var(--gjs-font-color);
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.5;
    }

    /* Common styles */
    /* .tab-button, */
    .panel-button {
        padding: 8px 12px;
        cursor: pointer;
        border-radius: 4px;
    }

    .panel-button:hover {
        background: var(--bulma-primary-soft);
    }

    /* .tab-button.is-active, */
    .panel-button.is-active {
        background: var(--bulma-primary);
        /* color: var(--gjs-font-color); */
        display: '';
    }

    .panel-button.is-active:hover {
        background: hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-l), 0.8);
        /* color: var(--gjs-font-color); */
        display: '';
    }

    /* .left-panel-content-pane:not(.is-active), */
    .right-panel-content-pane:not(.is-active),
    .modal:not(.is-active) {
        display: none;
    }

    .left-panel.gjs-pn-panel,
    .right-panel.gjs-pn-panel {
        padding: 0;
    }

    .hidden {
        display: none;
    }

    * ::-webkit-scrollbar {
        width: 10px;
    }

    * ::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1)
    }

    * ::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2)
    }

    /* Reset some default styling */
    .gjs-cv-canvas {
        top: 0;
        width: 100%;
        height: 100%;
    }

    .modal-card.is-fullheight {
        height: 80%;
    }

    .tabs:not(:last-child) {
        margin-bottom: 0 !important;
    }

    .tabs li.is-active a {
        color: var(--bulma-primary-40);
    }

    .gjs-sm-sector-title {
        padding: 9px 10px 9px 0px;
    }

    .gjs-field :not(.gjs-input-unit) input:not([type="range"]):focus,
    .gjs-field .gjs-field-units select:not(.gjs-input-unit):focus,
    .gjs-sm-field :not(.gjs-input-unit) input:not([type="range"]):focus,
    .gjs-sm-field .gjs-field-units select:not(.gjs-input-unit):focus,
    .gjs-select select:focus,
    .gjs-field.gjs-field-text input:focus,
    .gjs-field select#gjs-clm-states:focus {
        border-color: hsl(var(--bulma-input-focus-h), var(--bulma-input-focus-s), var(--bulma-input-focus-l));
        box-shadow: var(--bulma-input-focus-shadow-size) hsla(var(--bulma-input-focus-h), var(--bulma-input-focus-s), var(--bulma-input-focus-l), var(--bulma-input-focus-shadow-alpha));
    }

    .gjs-field :not(.gjs-input-unit) input:not([type="range"]),
    .gjs-field .gjs-field-units select:not(.gjs-input-unit),
    .gjs-sm-field :not(.gjs-input-unit) input:not([type="range"]),
    .gjs-sm-field .gjs-field-units select:not(.gjs-input-unit),
    .gjs-select select,
    .gjs-field.gjs-field-text input,
    .gjs-field select#gjs-clm-states {
        --bulma-input-h: var(--bulma-primary-h);
        --bulma-input-s: var(--bulma-primary-s);
        --bulma-input-l: var(--bulma-scheme-main-l);
        --bulma-input-border-style: solid;
        --bulma-input-border-width: var(--bulma-control-border-width);
        --bulma-input-border-l: var(--bulma-border-l);
        --bulma-input-border-l-delta: 0%;
        --bulma-input-hover-border-l-delta: var(--bulma-hover-border-l-delta);
        --bulma-input-active-border-l-delta: var(--bulma-active-border-l-delta);
        --bulma-input-focus-h: var(--bulma-focus-h);
        --bulma-input-focus-s: var(--bulma-focus-s);
        --bulma-input-focus-l: var(--bulma-focus-l);
        --bulma-input-focus-shadow-size: var(--bulma-focus-shadow-size);
        --bulma-input-focus-shadow-alpha: var(--bulma-focus-shadow-alpha);
        --bulma-input-color-l: var(--bulma-text-strong-l);
        --bulma-input-background-l: var(--bulma-scheme-main-l);
        --bulma-input-background-l-delta: 0%;
        --bulma-input-height: var(--bulma-control-height);
        --bulma-input-shadow: inset 0 0.0625em 0.125em hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-invert-l), 0.05);
        --bulma-input-placeholder-color: hsla(var(--bulma-text-h), var(--bulma-text-s), var(--bulma-text-strong-l), 0.3);
        --bulma-input-disabled-color: var(--bulma-text-weak);
        --bulma-input-disabled-background-color: var(--bulma-background);
        --bulma-input-disabled-border-color: var(--bulma-background);
        --bulma-input-disabled-placeholder-color: hsla(var(--bulma-text-h), var(--bulma-text-s), var(--bulma-text-weak-l), 0.3);
        --bulma-input-arrow: var(--bulma-link);
        --bulma-input-icon-color: var(--bulma-text-light);
        --bulma-input-icon-hover-color: var(--bulma-text-weak);
        --bulma-input-icon-focus-color: var(--bulma-link);
        /* --bulma-input-radius: var(--bulma-radius-small); */
        --bulma-input-radius: 2px;
        --bulma-control-padding-vertical: calc(0.5em - 1px);
        --bulma-control-padding-horizontal: calc(0.75em - 1px);
        padding: var(--bulma-control-padding-vertical) var(--bulma-control-padding-horizontal);
        border: 1px solid;
        border-color: hsl(var(--bulma-input-h), var(--bulma-input-s), calc(var(--bulma-input-border-l) + var(--bulma-input-border-l-delta)));
        border-radius: var(--bulma-input-radius);
        color: hsl(var(--bulma-input-h), var(--bulma-input-s), var(--bulma-input-color-l));
    }

    .gjs-field .gjs-field-units select.gjs-input-unit {
        color: var(--bulma-text);
    }

    .gjs-field-checkbox input:checked+.gjs-chk-icon {
        border-color: inherit;
    }

    .gjs-clm-tag-status svg,
    .gjs-clm-tag-close svg {
        vertical-align: middle;
        fill: var(--gjs-secondary-color);
    }

    .gjs-radio-item input:checked+.gjs-radio-item-label {
        background-color: hsl(var(--bulma-primary-h) var(--bulma-primary-s) var(--bulma-primary-l) / 20%);
    }

    .sp-container.sp-light.sp-input-disabled.sp-alpha-enabled.sp-palette-buttons-disabled.sp-initial-disabled.gjs-one-bg.gjs-two-color.gjs-editor-sp {
        /* position: fixed !important; */
        top: 50vh !important;
        transform: translateY(-50%) !important;
        /* left: auto; */
    }

    .gjs-pn-btn.gjs-pn-active {
        background-color: var(--bulma-primary);
        color: var(--bulma-primary-invert) !important;
    }

    .gjs-pn-btn.gjs-pn-active:hover {
        background-color: hsla(var(--bulma-primary-h), var(--bulma-primary-s), var(--bulma-primary-l), 0.8);
    }

    .gjs-pn-btn:hover:not(.gjs-disabled) {
        background-color: var(--bulma-primary-soft);
    }

    .gjs-pn-btn.gjs-disabled {
        cursor: default;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="gjs-editor-container"></div>



<!-- Modal for File Manager -->
<div id="fileManagerModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card is-fullheight">
        <div class="modal-card-body is-flex">
            <iframe class="is-flex-grow-1" id="fileManagerIframe"></iframe>
        </div>
    </div>
    <button class="modal-close is-large"></button>
</div>

<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

<?= $this->include('\Modules\PagingSystem\Views\Admin\editor_scripts') ?>
<?= $this->endSection() ?>