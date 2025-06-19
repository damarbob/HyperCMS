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
                    editor.Components.addComponent({
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
                iframe.src = `${config.baseUrl}admin/file-manager?requester_id=${config.requester}`;
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
            });
        };

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
                active: true,
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
                    title: `${window.hyper.lang.Admin.undo}`
                }
            });
            panels.addButton('options', {
                id: 'redo',
                className: 'fa-solid fa-rotate-right',
                command: 'core:redo',
                context: 'core:redo',
                attributes: {
                    title: `${window.hyper.lang.Admin.redo}`
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
                        <option value="desktop" selected>Desktop</option>
                        <option value="tablet">Tablet</option>
                        <option value="mobile-landscape">Mobile Landscape</option>
                        <option value="mobile-portrait">Mobile Portrait</option>
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
        };

        /**
         * Set up UI interactions (tabs, buttons, etc.)
         */
        const setupUiInteractions = () => {
            // Cache elements for Left Panel Management
            const leftContent = document.querySelector('.left-panel-content');
            const leftContentPanes = document.querySelectorAll('.left-panel-content-pane');
            const panelButtons = document.querySelectorAll('.panel-button');

            panelButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetId = btn.dataset.target;
                    if (!targetId) return; // in case dataset.target is missing
                    const targetEl = document.getElementById(targetId);

                    // Toggle off if the target pane is already active
                    if (targetEl && targetEl.classList.contains('is-active')) {
                        targetEl.classList.remove('is-active');
                        btn.classList.remove('is-active', 'has-text-primary-invert');
                        leftContent.style.display = 'none';
                        return;
                    }

                    // Show the left content wrapper
                    leftContent.style.display = 'flex';

                    // Remove active classes from all left panes and buttons
                    leftContentPanes.forEach(pane => pane.classList.remove('is-active'));
                    panelButtons.forEach(button => button.classList.remove('is-active', 'has-text-primary-invert'));

                    // Activate the selected pane and button
                    if (targetEl) {
                        targetEl.classList.add('is-active');
                    }
                    btn.classList.add('is-active', 'has-text-primary-invert');

                    // Update header text
                    const header = document.getElementById('leftPanelHeader');
                    if (header) {
                        header.textContent = btn.title;
                    }

                    // Show search input for blocks
                    const searchInput = document.getElementById('blocksSearchInput');
                    if (searchInput && targetId === 'blocks-manager') {
                        searchInput.style.display = 'block';
                    } else if (searchInput) {
                        searchInput.style.display = 'none';
                    }
                });
            });

            // Right panel tabs
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetId = tab.dataset.target;
                    if (!targetId) return;

                    // Update active tab
                    document.querySelectorAll('.tab-button').forEach(t =>
                        t.classList.toggle('is-active', t === tab)
                    );

                    // Show corresponding pane
                    document.querySelectorAll('.right-panel-content-pane').forEach(pane =>
                        pane.classList.toggle('is-active', pane.id === targetId)
                    );
                });
            });

            // Close modal button
            document.querySelector('.modal-close').addEventListener('click', closeFileManagerModal);

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

        /**
         * Show the no component selected state
         */
        const showNoComponentState = () => {
            const attributesContent = document.getElementById(ATTRIBUTES_CONTENT_ID);

            if (attributesContent) {
                attributesContent.innerHTML = `
                        <div class="no-component">
                            <i class="fas fa-mouse-pointer"></i>
                            <h3>Select a Component</h3>
                            <p>Click on any component in the editor to manage its attributes</p>
                        </div>
                    `;
            }

            currentComponent = null;
            attributeRows = [];
        };

        /**
         * Render the attributes panel for a component
         * @param {Component} component The selected component
         */
        const renderAttributesPanel = (component) => {
            currentComponent = component;
            const attrs = component.getAttributes();

            const attributesContent = document.getElementById(ATTRIBUTES_CONTENT_ID);

            if (!attributesContent) return;

            // Create panel content
            attributesContent.innerHTML = `
                    <div class="component-info">
                        <div class="component-type">
                            <i class="fas fa-cube"></i> ${component.getName() || component.get('type')} <span class="has-text-weight-light">(#${component.getId()})</span>
                        </div>
                    </div>
                    
                    <div class="section-title">
                        <i class="fas fa-list"></i> ${window.hyper.lang.PagingSystem.customAttributes}
                    </div>
                    
                    <div class="custom-attributes is-flex is-flex-direction-column">                        
                        <div class="attributes-list" id="attributesList"></div>
                        <button class="add-attribute is-align-self-flex-end mt-2" id="addAttribute" title="${window.hyper.lang.PagingSystem.addAttribute}">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <button class="update-btn button is-primary is-fullwidth mt-3" id="updateAttributes" disabled>
                        <span class="icon">
                            <i class="fas fa-sync-alt"></i>
                        </span>
                        <span>
                            ${window.hyper.lang.PagingSystem.updateAttribute}
                        </span>
                    </button>
                `;

            const attrsList = document.getElementById('attributesList');

            // Create attribute rows
            attributeRows = [];
            for (const [index, [key, value]] of Object.entries(Object.entries(attrs))) {
                if (key === 'id' || key === 'class') continue; // Skip built-in attributes
                addAttributeRow(attrsList, key, value, parseInt(index) + 1);
            }

            // Add empty row at the end
            addAttributeRow(attrsList);

            // Add event listeners
            document.getElementById('addAttribute').addEventListener('click', addNewAttribute);
            document.getElementById('updateAttributes').addEventListener('click', saveAttributes);
        };

        /**
         * Add an attribute row to the attributes list
         * @param {HTMLElement} container The container element
         * @param {string} key The attribute key
         * @param {string} value The attribute value
         */
        const addAttributeRow = (container, key = '', value = '', index = null) => {
            const row = document.createElement('div');
            row.className = 'attribute-row';

            const rowHeader = document.createElement('div');
            rowHeader.className = 'row-header';
            rowHeader.innerHTML = `Attribute ${index ? index : container.children.length + 1}`;
            rowHeader.style.display = 'flex';
            rowHeader.style.alignItems = 'center';
            rowHeader.style.justifyContent = 'space-between';
            rowHeader.style.width = '100%';
            row.appendChild(rowHeader);

            const keyInput = document.createElement('input');
            keyInput.type = 'text';
            keyInput.className = 'attr-key input';
            keyInput.placeholder = `${window.hyper.lang.PagingSystem.attribute}`;
            keyInput.value = key;

            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.className = 'attr-value input';
            valueInput.placeholder = `${window.hyper.lang.PagingSystem.value}`;
            valueInput.value = value;

            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-attribute';
            removeBtn.title = `${window.hyper.lang.PagingSystem.removeAttribute}`;
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';

            row.appendChild(keyInput);
            row.appendChild(valueInput);

            rowHeader.appendChild(removeBtn);

            // Add remove button functionality
            removeBtn.addEventListener('click', () => {
                row.remove();
                attributeRows = attributeRows.filter(r => r !== row);
                document.getElementById('updateAttributes').disabled = false;
            });


            container.appendChild(row);
            attributeRows.push(row);

            // Add change events
            const updateBtn = document.getElementById('updateAttributes');
            keyInput.addEventListener('input', (e) => {
                if (e.target.value.trim() === '') {
                    // Disable update button if key is empty
                    updateBtn.disabled = true;
                } else {
                    // Enable update button if key is not empty
                    updateBtn.disabled = false;
                }
            });
        };

        /**
         * Add a new attribute row
         */
        const addNewAttribute = () => {
            const attrsList = document.getElementById('attributesList');
            const lastRow = attributeRows[attributeRows.length - 1];
            const keyInput = lastRow.querySelector('.attr-key');
            const valueInput = lastRow.querySelector('.attr-value');

            // Only add if last row is not empty
            if (keyInput.value.trim() || valueInput.value.trim()) {
                addAttributeRow(attrsList);
            }
        };

        /**
         * Save attributes to the component
         */
        const saveAttributes = () => {
            if (!currentComponent) return;

            const attributes = {};

            attributeRows.forEach(row => {
                const keyInput = row.querySelector('.attr-key');
                const valueInput = row.querySelector('.attr-value');
                const key = keyInput.value.trim();
                const value = valueInput.value.trim();

                if (key) {
                    if (value === '') {
                        // If value is empty add only the key (like disabled)
                        attributes[key] = '';
                    } else {
                        attributes[key] = value;
                    }
                }
            });

            // Update component attributes
            currentComponent.setAttributes(attributes);

            // Disable update button
            document.getElementById('updateAttributes').disabled = true;
        };

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