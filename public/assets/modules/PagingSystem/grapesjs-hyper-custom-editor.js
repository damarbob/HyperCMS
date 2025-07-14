/**
 * GrapesJS Hyper Custom Editor Plugin
 * 
 * This plugin provides:
 * 1. Custom attribute management panel
 * 2. File manager integration for asset management
 * 3. Enhanced UI components for the editor
 * 
 * Usage:
 *   const editor = grapesjs.init({
 *     // ... standard configuration
 *     plugins: [grapesjs-hyper-custom-editor],
 *     pluginsOpts: {
 *       [grapesjs-hyper-custom-editor],: {
 *         baseUrl: 'https://your-domain.com',
 *         requester: 'unique-requester-id',
 *         // ... other options
 *       }
 *     }
 *   });
 * 
 * @param {grapesjs.Editor} editor The editor instance
 * @param {Object} opts Plugin opts
 * @param {string} [opts.baseUrl] Base URL for file manager communication
 * @param {string} [opts.requester] Unique identifier for the requester
 */
grapesjs.plugins.add(
    "grapesjs-hyper-custom-editor",
    function (
        editor, opts = {}) {
        // const customEditorPlugin = (editor, opts = {}) => {
        const config = {
            baseUrl: opts.baseUrl || '',
            requester: opts.requester || 'default',
            // Default plugin opts
            ...opts
        };

        // Constants
        const FILE_MANAGER_MODAL_ID = 'fileManagerModal';
        const ATTRIBUTES_CONTENT_ID = 'attributesContent';

        // State variables
        let currentComponent = null;
        let attributeRows = [];

        /**
         * Initialize the plugin
         */
        const init = () => {
            // Set up message listener for file manager
            setupMessageListener();

            // Set up editor event listeners
            setupEventListeners();

            // Add custom commands
            addCustomCommands();

            // Add custom panels
            addCustomPanels();

            // Add UI buttons
            addUiButtons();

            // Set up UI interactions
            setupUiInteractions();

            // Render blocks manager
            renderBlocksManager();

            // Log initialization
            if (window.hyper.config.environment !== 'production') {
                console.log('Hyper Custom Editor Plugin initialized with options:', config);
            }

        };

        /**
         * Set up the message listener for file manager communication
         */
        const setupMessageListener = () => {
            window.addEventListener('message', event => {
                // Security: validate event origin
                if (!isValidOrigin(event.origin)) return;

                if (event.data && event.data.action === `filesSelected_r${config.requester}`) {
                    handleFilesSelected(event.data.data);
                }
            });
        };

        /**
         * Validate if the event origin matches the base URL
         * @param {string} origin The event origin
         * @returns {boolean} True if valid, false otherwise
         */
        const isValidOrigin = (origin) => {
            return window.hyper.util.uri.areUrisEqual(origin, config.baseUrl)
        };

        /**
         * Handle files selected from the file manager
         * @param {Array} files Array of file URLs
         */
        const handleFilesSelected = (files) => {
            if (files.length === 0) return;

            files.forEach(file => {
                const extension = getFileExtension(file);

                if (isImage(extension)) {
                    // Add to asset manager
                    editor.AssetManager.add({
                        src: file,
                        type: 'image',
                        label: `Image ${editor.AssetManager.getAll().length + 1}`
                    });

                    // Add as a component
                    editor.getSelected().addComponent({
                        type: 'image',
                        src: file,
                        style: {
                            width: '100%',
                            height: 'auto'
                        }
                    });
                } else {
                    window.hyper.factory.swal.error(window.hyper.lang.Admin.error);
                }
            });

            // Close the modal
            closeFileManagerModal();
        };

        /**
         * Extract file extension from URL
         * @param {string} fileUrl The file URL
         * @returns {string} The file extension
         */
        const getFileExtension = (fileUrl) => {
            return fileUrl.slice((fileUrl.lastIndexOf(".") - 1 >>> 0) + 2).toLowerCase();
        };

        /**
         * Check if file is an image based on extension
         * @param {string} ext The file extension
         * @returns {boolean} True if image, false otherwise
         */
        const isImage = (ext) => {
            return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
        };

        /**
         * Open the file manager modal
         */
        const openFileManagerModal = () => {
            const modal = document.getElementById(FILE_MANAGER_MODAL_ID);
            const iframe = document.getElementById('fileManagerIframe');

            if (modal && iframe) {
                if (!iframe.getAttribute('src')) {
                    iframe.src = `${config.baseUrl}admin/file-manager?requester_id=${config.requester}`;
                }
                modal.classList.add('is-active');
            }
        };

        /**
         * Close the file manager modal
         */
        const closeFileManagerModal = () => {
            const modal = document.getElementById(FILE_MANAGER_MODAL_ID);
            if (modal) modal.classList.remove('is-active');
        };

        /**
         * Set up editor event listeners
         */
        const setupEventListeners = () => {
            // Component selection
            editor.on('component:selected', model => {
                renderAttributesPanel(model);
            });

            // Component deselection
            editor.on('component:deselected', () => {
                if (editor.getSelected()) {
                    renderAttributesPanel(editor.getSelected());
                    return;
                }
                showNoComponentState();
            });

            // Selector type change
            editor.on('component:toggled', () => {
                const selected = editor.getSelected();

                updateStyleManagerView(selected);

                if (selected) {
                    renderAttributesPanel(selected);
                } else {
                    showNoComponentState();
                }
            });

            editor.on('load', () => {
                updateStyleManagerView(editor.getSelected());

                // Initialize buttons and update state
                updateUndoRedoButtons();

                // Set up event listeners
                editor.on('update undo redo', updateUndoRedoButtons);

                // $('#undo-button, #redo-button').on('click', function () { updateUndoRedoButtons(); })
                $('#undo-button, #redo-button').on('click', () => { updateUndoRedoButtons(); })
            });

        };

        // Update button state
        function updateUndoRedoButtons() {
            const panels = editor.Panels;
            const undoButton = panels.getButton('options', 'undo');
            const redoButton = panels.getButton('options', 'redo');

            if (!undoButton || !redoButton) return;

            // Update undo button state
            const hasUndo = editor.UndoManager.hasUndo();
            undoButton.set('disable', !hasUndo);

            // Update redo button state
            const hasRedo = editor.UndoManager.hasRedo();
            redoButton.set('disable', !hasRedo);
        }

        /**
         * Add custom commands to the editor
         */
        const addCustomCommands = () => {
            const commands = editor.Commands;
            // Open file manager command
            commands.add('open-file-manager', {
                run: () => openFileManagerModal()
            });

            // Device commands
            commands.add('set-device-desktop', {
                run: editor => editor.setDevice('Desktop'),
                stop: editor => {
                    // deselectDevices();
                    // editor.runCommand('set-device-desktop');
                    // editor.Panels.getButton('devices', 'device-desktop').set('active', true)
                }
            });

            commands.add('set-device-tablet', {
                run: editor => editor.setDevice('Tablet'),
                stop: editor => {
                    // deselectDevices();
                    // editor.runCommand('set-device-desktop');
                    // editor.Panels.getButton('devices', 'device-desktop').set('active', true)
                }
            });

            commands.add('set-device-mobile-landscape', {
                run: editor => editor.setDevice('Mobile landscape'),
                stop: editor => {
                    // deselectDevices();
                    // editor.runCommand('set-device-desktop');
                    // editor.Panels.getButton('devices', 'device-desktop').set('active', true)
                }
            });

            commands.add('set-device-mobile-portrait', {
                run: editor => editor.setDevice('Mobile portrait'),
                stop: editor => {
                    // deselectDevices();
                    // editor.runCommand('set-device-desktop');
                    // editor.Panels.getButton('devices', 'device-desktop').set('active', true)
                }
            });

            // Toggle selector mode command
            commands.add('grapesjs-hyper-custom-editor:toggle-selector-mode', {
                run: function (editor) {
                    const selectors = editor.Selectors;
                    const currentOption = selectors.getComponentFirst();
                    selectors.setComponentFirst(!currentOption);

                    // Trigger an event to update the UI
                    editor.trigger('component:toggled');

                },
                stop: function (editor) {
                    const selectors = editor.Selectors;
                    const currentOption = selectors.getComponentFirst();
                    selectors.setComponentFirst(!currentOption);

                    // Trigger an event to update the UI
                    editor.trigger('component:toggled');
                }
            });

            // Toggle dark mode command
            commands.add('grapesjs-hyper-custom-editor:toggle-dark-mode', {
                run(editor) {
                    $('html').attr('data-theme', 'dark');
                },
                stop(editor) {
                    $('html').attr('data-theme', 'light');
                }
            });

            // Show project data command
            commands.add('grapesjs-hyper-custom-editor:show-project-data', {
                run(editor) {
                    const codeViewer = this.getCodeViewer();
                    const codeContent = JSON.stringify(editor.getProjectData(), null, 2);
                    editor.Modal.open({
                        title: `${window.hyper.lang.PagingSystem.projectData}`,
                        content: codeViewer.getElement()
                    }).onceClose(() => editor.stopCommand('grapesjs-hyper-custom-code:show-project-data'));
                    codeViewer.setContent(codeContent ?? '');
                    codeViewer.refresh();
                    setTimeout(() => codeViewer.focus(), 0);
                },

                getCodeViewer() {
                    if (!this.codeViewer) {
                        this.codeViewer = editor.CodeManager.createViewer({
                            codeName: 'htmlmixed',
                            theme: 'hopscotch',
                            readOnly: true,
                            autoBeautify: true,
                            autoCloseTags: true,
                            autoCloseBrackets: true,
                            lineWrapping: true,
                            styleActiveLine: true,
                            smartIndent: true,
                            indentWithTabs: true
                        });
                    }

                    return this.codeViewer;
                },
            });
        };


        /**
         * Add Panels
         */
        const addCustomPanels = () => {
            const panels = editor.Panels;

            panels.addPanel({
                id: 'top-panel',
                el: '.top-panel',
            });

            panels.addPanel({
                id: 'left-panel',
                el: '.left-panel',
            });

            panels.addPanel({
                id: 'devices',
                el: '.devices-panel',
            });

            panels.addPanel({
                id: 'options',
                el: '.options-panel',
            });

            panels.addPanel({
                id: 'right-panel',
                el: '.right-panel',
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
            });
        };

        /**
         * Add UI buttons to the editor
         */
        const addUiButtons = () => {
            const panels = editor.Panels;

            if (window.hyper.config.environment !== 'production') {
                panels.addButton('options', {
                    id: 'grapesjs-hyper-custom-editor:show-project-data',
                    className: 'fa-solid fa-file-code',
                    command: 'grapesjs-hyper-custom-editor:show-project-data',
                    attributes: {
                        title: `${window.hyper.lang.PagingSystem.showProjectData}`
                    },
                    context: 'grapesjs-hyper-custom-editor:show-project-data',
                });
            }

            panels.addButton('options', {
                id: 'grapesjs-hyper-custom-editor:toggle-dark-mode',
                className: 'fa-solid fa-moon',
                command: 'grapesjs-hyper-custom-editor:toggle-dark-mode',
                attributes: {
                    title: `${window.hyper.lang.PagingSystem.toggleDarkMode}`
                },
                context: 'grapesjs-hyper-custom-editor:toggle-dark-mode',
                active: $('html').attr('data-theme') === 'dark' || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            });
            panels.addButton('options', {
                id: 'gjs-sw-visibility',
                className: 'fa-solid fa-border-none',
                command: 'sw-visibility',
                attributes: {
                    title: `${window.hyper.lang.PagingSystem.swVisibility}`
                },
                context: 'sw-visibility',
            });
            panels.addButton('options', {
                id: 'gjs-open-import-code',
                className: 'fa-solid fa-file-import',
                command: 'grapesjs-import-code',
                context: 'grapesjs-import-code',
                attributes: {
                    title: `${window.hyper.lang.PagingSystem.importCode}`
                }
            });
            panels.addButton('options', {
                id: 'undo',
                className: 'fa-solid fa-rotate-left',
                command: 'core:undo',
                context: 'core:undo',
                attributes: {
                    title: `${window.hyper.lang.Admin.undo}`,
                    id: 'undo-button'
                }
            });
            panels.addButton('options', {
                id: 'redo',
                className: 'fa-solid fa-rotate-right',
                command: 'core:redo',
                context: 'core:redo',
                attributes: {
                    title: `${window.hyper.lang.Admin.redo}`,
                    id: 'redo-button'
                }
            });
            panels.addButton('options', {
                id: 'gjs-open-view-code',
                className: 'fa-solid fa-code',
                command: 'core:open-code',
                context: 'core:open-code',
                attributes: {
                    title: `${window.hyper.lang.PagingSystem.viewCode}`
                }
            });
            panels.addButton('options', {
                id: 'gjs-open-preview-webpage',
                className: 'fa-solid fa-eye',
                command: 'core:preview',
                preview: 'core:preview',
                attributes: {
                    title: `${window.hyper.lang.PagingSystem.preview}`
                }
            });

            // File manager button
            panels.addButton('options', {
                id: 'file-manager',
                className: 'fa-solid fa-folder-open',
                command: 'open-file-manager',
                context: 'open-file-manager',
                attributes: { title: `${window.hyper.lang.Admin.fileManager}` }
            });

            // Device selector dropdown
            $('.devices-panel').append(`
                <div class="select is-small is-primary">
                    <select id="deviceSelector">
                        <option value="desktop" selected>${window.hyper.lang.PagingSystem.desktop}</option>
                        <option value="tablet">${window.hyper.lang.PagingSystem.tablet}</option>
                        <option value="mobile-landscape">${window.hyper.lang.PagingSystem.mobileLandscape}</option>
                        <option value="mobile-portrait">${window.hyper.lang.PagingSystem.mobilePortrait}</option>
                    </select>
                </div>`
            );

            // Device selector change event
            $('#deviceSelector').on('change', function () {
                const selectedDevice = $(this).val();
                switch (selectedDevice) {
                    case 'desktop':
                        editor.runCommand('set-device-desktop');
                        break;
                    case 'tablet':
                        editor.runCommand('set-device-tablet');
                        break;
                    case 'mobile-landscape':
                        editor.runCommand('set-device-mobile-landscape');
                        break;
                    case 'mobile-portrait':
                        editor.runCommand('set-device-mobile-portrait');
                        break;
                    default:
                        editor.runCommand('set-device-desktop');
                }
            });

            // Device buttons
            // panels.addButton('devices', {
            //     id: 'device-desktop',
            //     className: 'fa-solid fa-desktop',
            //     command: 'set-device-desktop',
            //     attributes: { title: 'Desktop' }
            // });

            // panels.addButton('devices', {
            //     id: 'device-tablet',
            //     className: 'fa-solid fa-tablet-screen-button',
            //     command: 'set-device-tablet',
            //     attributes: { title: 'Tablet' }
            // });

            // panels.addButton('devices', {
            //     id: 'device-mobile-landscape',
            //     className: 'fa-solid fa-mobile-screen-button fa-rotate-270',
            //     command: 'set-device-mobile-landscape',
            //     attributes: { title: 'Mobile Landscape' }
            // });

            // panels.addButton('devices', {
            //     id: 'device-mobile-portrait',
            //     className: 'fa-solid fa-mobile-screen-button',
            //     command: 'set-device-mobile-portrait',
            //     attributes: { title: 'Mobile Portrait' }
            // });
        };

        /**
         * Update the style manager view based on selection
         * @param {Model} selected selected component
         * @returns {void}
         */
        const updateStyleManagerView = (selected) => {
            document.getElementById('style-manager').style.display = selected ? '' : 'none';
            document.getElementById('selector-manager').style.display = selected ? '' : 'none';
            document.getElementById('selector-mode').style.display = selected ? '' : 'none';
            document.getElementById('no-select-state').style.display = selected ? 'none' : '';

            // $('#component-editor').css('display', selected ? '' : 'none');
            // $('#no-component-state').css('display', selected ? 'none' : '');
        };

        /**
         * Set up UI interactions (tabs, buttons, etc.)
         */
        const setupUiInteractions = () => {
            // Cache DOM elements
            const $leftContent = $('.left-panel-content');
            const $leftContentPanes = $('.left-panel-content-pane');
            const $panelButtons = $('.panel-button');
            const $header = $('#leftPanelHeader');
            const $searchInput = $('#blocksSearchInput');
            const $tabButtons = $('.tab-button');
            const $rightPanes = $('.right-panel-content-pane');
            const $appVersion = $('#appVersion');

            // Left Panel Management
            $panelButtons.on('click', function () {
                const $btn = $(this);
                const targetId = $btn.data('target');

                if (!targetId) return;

                const $targetEl = $(`#${targetId}`);
                const wasActive = $targetEl.hasClass('is-active'); // Selected target currently active
                const anyActive = $leftContentPanes.hasClass('is-active'); // Any panes currently active

                // Remove active class
                $leftContentPanes.removeClass('is-active');
                $panelButtons.removeClass('is-active has-text-primary-invert');

                if (wasActive) {
                    // If selected target currently active, hide left panel
                    $targetEl.fadeOut(200, () => {
                        $leftContent.hide(300);
                        $appVersion.show(300);
                    });

                } else if (anyActive) {
                    // If selected target not active but there is active pane, hide panes and show the target pane
                    $leftContentPanes.fadeOut(200);

                    $targetEl.fadeIn(200);
                    $targetEl.addClass('is-active');

                    $btn.addClass('is-active has-text-primary-invert');

                } else {
                    // If no active pane, show pane
                    $leftContent.show(300, () => {
                        $targetEl.fadeIn(200);
                        $targetEl.addClass('is-active');
                    });
                    $btn.addClass('is-active has-text-primary-invert');

                    $appVersion.hide(300);
                }
                // Update header text
                if ($header.length) {
                    $header.text($btn.attr('title') || '');
                }

                // Toggle search input
                if ($searchInput.length) {
                    (targetId === 'blocks-manager') ? $searchInput.fadeIn(200) : $searchInput.fadeOut(200);
                }
            });

            // Right Panel Tabs
            $tabButtons.on('click', function () {
                const $tab = $(this);
                const targetId = $tab.data('target');

                if (!targetId) return;

                // Update active tab
                $tabButtons.removeClass('is-active');
                $tab.addClass('is-active');

                // Show corresponding pane
                $rightPanes.removeClass('is-active');
                $(`#${targetId}`).addClass('is-active');

                if (targetId === 'component-editor-container') {
                    editor.runCommand('open-code');
                }
            });

            // Close modal button
            $('.modal-close').on('click', closeFileManagerModal);

            // Selector mode
            // Delegated event listener for buttons within the container "selector-mode-buttons"
            $('.selector-mode-buttons').on('click', '.selector-mode-button', function () {
                // Remove classes from all buttons
                $('.selector-mode-button').removeClass('is-selected is-primary');
                // Add classes to the clicked button
                $(this).addClass('is-selected is-primary');

                // Get the ID of the clicked button
                const mode = $(this).attr('id');

                // Update the editor's selector mode based on the clicked button
                switch (mode) {
                    case 'selectorModeComponent':
                        editor.Selectors.setComponentFirst(true);
                        editor.trigger('component:toggled');
                        break;
                    case 'selectorModeClass':
                        editor.Selectors.setComponentFirst(false);
                        editor.trigger('component:toggled');
                        break;
                    default:
                        editor.Selectors.setComponentFirst(true);
                        editor.trigger('component:toggled');
                        break;
                }
            });

        };

        // In your plugin file (GrapesJS plugin)
        const ATTRIBUTES_PANEL_ID = 'attributes-panel';
        const NO_TRAIT_STATE_ID = 'no-trait-state';

        const showNoComponentState = () => {
            $(`#${NO_TRAIT_STATE_ID}`).show();
            $(`#${ATTRIBUTES_PANEL_ID}`).hide();
            currentComponent = null;
        };

        const renderAttributesPanel = (component) => {
            currentComponent = component;

            // Hide "no component" state and show attributes panel
            $(`#${NO_TRAIT_STATE_ID}`).hide();
            $(`#${ATTRIBUTES_PANEL_ID}`).show();

            // Update component info
            $('#component-type-name').text(component.getName() || component.get('type'));
            $('#component-id').text(`(#${component.getId()})`);

            // Clear existing attributes
            $('#attributesList').empty();

            const attrs = component.getAttributes();

            // Create attribute rows
            for (const [key, value] of Object.entries(attrs)) {
                if (key === 'id' || key === 'class') continue;
                addAttributeRow(key, value);
            }
        };

        const saveAttributes = () => {
            if (!currentComponent) return;

            const attributes = {};
            let isValid = true;

            $('.attribute-row').each(function () {
                const key = $(this).find('.attr-key').val().trim();
                const value = $(this).find('.attr-value').val().trim();

                if (key) {
                    attributes[key] = value;
                } else if ($(this).find('.attr-value').val().trim()) {
                    isValid = false;
                }
            });

            if (!isValid) {
                alert('Please enter attribute names for all values');
                return;
            }

            currentComponent.setAttributes(attributes);
            $('#updateAttributes').prop('disabled', true);
        };

        const addNewAttribute = () => {
            const $rows = $attributesList.children();
            if ($rows.length === 0) return addAttributeRow();

            const $lastRow = $rows.last();
            const key = $lastRow.find('.attr-key').val().trim();
            const value = $lastRow.find('.attr-value').val().trim();

            if (key || value) addAttributeRow();
        };

        // Cache frequently used elements
        const $attributesList = $('#attributesList');
        const $updateButton = $('#updateAttributes');
        const $addButton = $('#addAttribute');

        // Centralized validation for all rows
        const validateAllAttributes = () => {
            let allValid = true;
            $attributesList.find('.attribute-row').each(function () {
                if ($(this).find('.attr-key').val().trim() === '') {
                    allValid = false;
                    return false; // Break early
                }
            });
            $updateButton.prop('disabled', !allValid);
        };

        // Renumber rows sequentially
        const renumberRows = () => {
            $attributesList.find('.attribute-row').each(function (index) {
                $(this).find('.attribute-header-text')
                    .text(`${window.hyper.lang.PagingSystem.attribute} ${index + 1}`);
            });
        };

        // Event delegation for dynamic elements
        $attributesList
            .on('click', '.remove-attribute', function () {
                $(this).closest('.attribute-row').remove();
                renumberRows();
                validateAllAttributes();
            })
            .on('input', '.attr-key, .attr-value', validateAllAttributes);

        // Static event bindings
        $addButton.on('click', addNewAttribute);
        $updateButton.on('click', saveAttributes); // Your save function

        const addAttributeRow = (key = '', value = '') => {
            const rowHTML = `
        <div class="attribute-row">
            <div class="row-header is-flex is-align-items-center is-justify-content-space-between" style="width:100%">
                <span class="attribute-header-text">${window.hyper.lang.PagingSystem.attribute} TEMP</span>
                <button class="remove-attribute" title="${window.hyper.lang.PagingSystem.removeAttribute}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="text" class="attr-key input" 
                   placeholder="${window.hyper.lang.PagingSystem.attribute}" 
                   value="${escapeHTML(key)}">
            <input type="text" class="attr-value input" 
                   placeholder="${window.hyper.lang.PagingSystem.value}" 
                   value="${escapeHTML(value)}">
        </div>`;

            $attributesList.append(rowHTML);
            renumberRows(); // Renumber all rows after adding
            validateAllAttributes(); // Validate after adding
        };

        // XSS protection
        const escapeHTML = str => str.replace(/[&<>"']/g,
            m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[m]));

        // Initial validation
        validateAllAttributes();

        const renderBlocksManager = () => {
            const blocksManagerEl = document.getElementById('blocks-manager');
            if (!blocksManagerEl) return;

            const blockManager = editor.Blocks;
            const blocks = blockManager.getAll();
            let filteredBlocks = [];

            // Event listener for search input
            document.getElementById('blocksSearchInput').addEventListener('input', (e) => {

                if (!e.target.value || e.target.value.trim() === '') {
                    // If search input is empty, render all blocks
                    blockManager.render();
                } else {
                    // Filter blocks based on search term
                    filteredBlocks = blocks.filter(block => {
                        const searchTerm = e.target.value.toLowerCase();
                        return block.getLabel().toLowerCase().includes(searchTerm) ||
                            block.getCategoryLabel().toLowerCase().includes(searchTerm);
                    });
                    // Render the filtered blocks
                    blockManager.render(filteredBlocks);
                }

            });

        };

        // Initialize the plugin
        init();

        // Return the plugin API if needed
        // return {
        //     name: 'customEditorPlugin',
        //     version: '1.0.0',
        //     openFileManager: openFileManagerModal,
        //     closeFileManager: closeFileManagerModal
        // };
    });