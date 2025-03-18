<?= $this->extend('admin/layout/page_blank') ?>

<?= $this->section('content') ?>
<div class="panel__top">
    <div class="panel__basic-actions"></div>
    <div class="panel__devices"></div>
    <div class="panel__switcher"></div>
</div>
<div class="editor-row">
    <div class="editor-canvas">
        <div id="gjs">...</div>
    </div>
    <div class="panel__right">
        <div class="layers-container"></div>
        <div class="styles-container"></div>
        <div class="traits-container"></div>
    </div>
</div>
<div id="blocks"></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/grapes.min.js" integrity="sha256-/WoKyVG/rkPGHHHqcWUCUrZEAv4MNxbHL1wcN7Y5o30=" crossorigin="anonymous"></script>
<script type="text/javascript">
    var editor = grapesjs.init({
        container: '#gjs',
        width: 'auto',
        height: '300px',
        fromElement: true,
        deviceManager: {
            devices: [{
                    name: 'Desktop',
                    width: '', // default size
                },
                {
                    name: 'Mobile',
                    width: '320px', // this value will be used on canvas width
                    widthMedia: '480px', // this value will be used in CSS @media
                },
            ],
        },
        layerManager: {
            appendTo: '.layers-container',
        },
        // The Selector Manager allows to assign classes and
        // different states (eg. :hover) on components.
        // Generally, it's used in conjunction with Style Manager
        // but it's not mandatory
        selectorManager: {
            appendTo: '.styles-container',
        },
        styleManager: {
            appendTo: '.styles-container',
            sectors: [{
                    name: 'Dimension',
                    open: false,
                    // Use built-in properties
                    buildProps: ['width', 'min-height', 'padding'],
                    // Use `properties` to define/override single property
                    properties: [{
                        // Type of the input,
                        // options: integer | radio | select | color | slider | file | composite | stack
                        type: 'integer',
                        name: 'The width', // Label for the property
                        property: 'width', // CSS property (if buildProps contains it will be extended)
                        units: ['px', '%'], // Units, available only for 'integer' types
                        defaults: 'auto', // Default value
                        min: 0, // Min value, available only for 'integer' types
                    }, ],
                },
                {
                    name: 'Extra',
                    open: false,
                    buildProps: ['background-color', 'box-shadow', 'custom-prop'],
                    properties: [{
                        id: 'custom-prop',
                        name: 'Custom Label',
                        property: 'font-size',
                        type: 'select',
                        defaults: '32px',
                        // List of options, available only for 'select' and 'radio'  types
                        options: [{
                                value: '12px',
                                name: 'Tiny'
                            },
                            {
                                value: '18px',
                                name: 'Medium'
                            },
                            {
                                value: '32px',
                                name: 'Big'
                            },
                        ],
                    }, ],
                },
            ],
        },
        traitManager: {
            appendTo: '.traits-container',
        },
        StorageManager: false,
        // We define a default panel as a sidebar to contain layers
        panels: {
            defaults: [{
                    id: 'panel-devices',
                    el: '.panel__devices',
                    buttons: [{
                            id: 'device-desktop',
                            label: 'D',
                            command: 'set-device-desktop',
                            active: true,
                            togglable: false,
                        },
                        {
                            id: 'device-mobile',
                            label: 'M',
                            command: 'set-device-mobile',
                            togglable: false,
                        },
                    ],
                },
                {
                    id: 'layers',
                    el: '.panel__right',
                    // Make the panel resizable
                    resizable: {
                        maxDim: 350,
                        minDim: 200,
                        tc: false, // Top handler
                        cl: true, // Left handler
                        cr: false, // Right handler
                        bc: false, // Bottom handler
                        // Being a flex child we need to change `flex-basis` property
                        // instead of the `width` (default)
                        keyWidth: 'flex-basis',
                    },
                },
                {
                    id: 'panel-switcher',
                    el: '.panel__switcher',
                    buttons: [{
                            id: 'show-layers',
                            active: true,
                            label: 'Layers',
                            command: 'show-layers',
                            // Once activated disable the possibility to turn it off
                            togglable: false,
                        },
                        {
                            id: 'show-style',
                            active: true,
                            label: 'Styles',
                            command: 'show-styles',
                            togglable: false,
                        },
                        {
                            id: 'show-traits',
                            active: true,
                            label: 'Traits',
                            command: 'show-traits',
                            togglable: false,
                        },
                    ],
                },
            ],
        },
        blockManager: {
            appendTo: '#blocks',
            blocks: [{
                    id: 'section', // id is mandatory
                    label: '<b>Section</b>', // You can use HTML/SVG inside labels
                    attributes: {
                        class: 'gjs-block-section'
                    },
                    content: `<section>
          <h1>This is a simple title</h1>
          <div>This is just a Lorem text: Lorem ipsum dolor sit amet</div>
        </section>`,
                },
                {
                    id: 'text',
                    label: 'Text',
                    content: '<div data-gjs-type="text">Insert your text here</div>',
                },
                {
                    id: 'image',
                    label: 'Image',
                    // Select the component once it's dropped
                    select: true,
                    // You can pass components as a JSON instead of a simple HTML string,
                    // in this case we also use a defined component type `image`
                    content: {
                        type: 'image'
                    },
                    // This triggers `active` event on dropped components and the `image`
                    // reacts by opening the AssetManager
                    activate: true,
                },
            ],
        },
    });
    // Define commands
    // Commands
    editor.Commands.add('set-device-desktop', {
        run: (editor) => editor.setDevice('Desktop'),
    });
    editor.Commands.add('set-device-mobile', {
        run: (editor) => editor.setDevice('Mobile'),
    });
    editor.Commands.add('show-layers', {
        getRowEl(editor) {
            return editor.getContainer().closest('.editor-row');
        },
        getLayersEl(row) {
            return row.querySelector('.layers-container');
        },

        run(editor, sender) {
            const lmEl = this.getLayersEl(this.getRowEl(editor));
            lmEl.style.display = '';
        },
        stop(editor, sender) {
            const lmEl = this.getLayersEl(this.getRowEl(editor));
            lmEl.style.display = 'none';
        },
    });
    editor.Commands.add('show-styles', {
        getRowEl(editor) {
            return editor.getContainer().closest('.editor-row');
        },
        getStyleEl(row) {
            return row.querySelector('.styles-container');
        },

        run(editor, sender) {
            const smEl = this.getStyleEl(this.getRowEl(editor));
            smEl.style.display = '';
        },
        stop(editor, sender) {
            const smEl = this.getStyleEl(this.getRowEl(editor));
            smEl.style.display = 'none';
        },
    });
    editor.Commands.add('show-traits', {
        getTraitsEl(editor) {
            const row = editor.getContainer().closest('.editor-row');
            return row.querySelector('.traits-container');
        },
        run(editor, sender) {
            this.getTraitsEl(editor).style.display = '';
        },
        stop(editor, sender) {
            this.getTraitsEl(editor).style.display = 'none';
        },
    });
    editor.BlockManager.add('my-block-id', {
        label: 'DIV',
        category: 'Basic',
        content: {
            tagName: 'div',
            draggable: true,
            attributes: {
                'some-attribute': 'some-value'
            },
            components: [{
                    tagName: 'span',
                    content: '<b>Some static content</b>',
                },
                {
                    tagName: 'div',
                    // use `content` for static strings, `components` string will be parsed
                    // and transformed in Components
                    components: '<span>HTML at some point</span>',
                },
            ],
        },
    });
    editor.Panels.addPanel({
        id: 'panel-top',
        el: '.panel__top',
    });
    editor.Panels.addPanel({
        id: 'basic-actions',
        el: '.panel__basic-actions',
        buttons: [{
                id: 'visibility',
                active: true, // active by default
                className: 'btn-toggle-borders',
                label: '<u>B</u>',
                command: 'sw-visibility', // Built-in command
            },
            {
                id: 'export',
                className: 'btn-open-export',
                label: 'Exp',
                command: 'export-template',
                context: 'export-template', // For grouping context of buttons from the same panel
            },
            {
                id: 'show-json',
                className: 'btn-show-json',
                label: 'JSON',
                context: 'show-json',
                command(editor) {
                    editor.Modal.setTitle('Components JSON')
                        .setContent(
                            `<textarea style="width:100%; height: 250px;">
            ${JSON.stringify(editor.getComponents())}
          </textarea>`,
                        )
                        .open();
                },
            },
        ],
    });
</script>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/css/grapes.min.css" integrity="sha256-1I3el0pvNWfTUd1SEXRESKNhHSpnELFugVLiTDt//cY=" crossorigin="anonymous">
<style>
    /* Reset some default styling */
    .gjs-cv-canvas {
        top: 0;
        width: 100%;
        height: 100%;
    }

    .gjs-block {
        width: auto;
        height: auto;
        min-height: auto;
    }

    .panel__top {
        padding: 0;
        width: 100%;
        display: flex;
        position: initial;
        justify-content: center;
        justify-content: space-between;
    }

    .panel__devices {
        position: initial;
    }

    .panel__basic-actions {
        position: initial;
    }

    .editor-row {
        display: flex;
        justify-content: flex-start;
        align-items: stretch;
        flex-wrap: nowrap;
        height: 300px;
    }

    .editor-canvas {
        flex-grow: 1;
    }

    .panel__right {
        flex-basis: 230px;
        position: relative;
        overflow-y: auto;
    }

    .panel__switcher {
        position: initial;
    }
</style>
<?= $this->endSection() ?>