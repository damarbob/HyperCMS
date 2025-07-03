grapesjs.plugins.add("grapesjs-bootstrap-button", function (editor, opts = {}) {
    const defaults = {
        buttonTypes: [
            { id: 'primary', name: 'Primary', class: 'btn-primary' },
            { id: 'secondary', name: 'Secondary', class: 'btn-secondary' },
            { id: 'success', name: 'Success', class: 'btn-success' },
            { id: 'danger', name: 'Danger', class: 'btn-danger' },
            { id: 'warning', name: 'Warning', class: 'btn-warning' },
            { id: 'info', name: 'Info', class: 'btn-info' },
            { id: 'light', name: 'Light', class: 'btn-light' },
            { id: 'dark', name: 'Dark', class: 'btn-dark' },
            { id: 'link', name: 'Link', class: 'btn-link' }
        ],
        buttonSizes: [
            { id: '', name: 'Default' },
            { id: 'btn-sm', name: 'Small' },
            { id: 'btn-lg', name: 'Large' }
        ],
        buttonGroupSizes: [
            { id: '', name: 'Default' },
            { id: 'btn-group-sm', name: 'Small' },
            { id: 'btn-group-lg', name: 'Large' }
        ],
        defaultText: 'Button',
        defaultType: 'primary',
        defaultSize: ''
    };

    const options = { ...defaults, ...opts };

    // BUTTON COMPONENT
    editor.Components.addType("bs-button", {
        isComponent: el => el.tagName === 'A' && el.classList.contains('bs-button'),

        model: {
            defaults: {
                tagName: 'a',
                name: 'Button',
                draggable: true,
                droppable: false,
                editable: true,
                attributes: {
                    class: `btn btn-${options.defaultType} bs-button`,
                    href: '#'
                },
                components: {
                    type: 'text',
                    content: options.defaultText,
                    editable: true
                },
                traits: [
                    {
                        type: 'select',
                        label: 'Button Type',
                        name: 'buttonType',
                        options: options.buttonTypes,
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Button Size',
                        name: 'buttonSize',
                        options: options.buttonSizes,
                        changeProp: true
                    },
                    {
                        type: 'text',
                        label: 'Link URL',
                        name: 'href',
                        placeholder: 'https://example.com'
                    },
                    {
                        type: 'checkbox',
                        label: 'Outline Style',
                        name: 'outline',
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Block Button',
                        name: 'block',
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Disabled',
                        name: 'disabled',
                        changeProp: true
                    }
                ],
                // Default trait values
                buttonType: options.defaultType,
                buttonSize: options.defaultSize,
                outline: false,
                block: false,
                disabled: false
            },

            init() {
                this.on('change:buttonType', this.updateType);
                this.on('change:buttonSize', this.updateSize);
                this.on('change:outline', this.updateOutline);
                this.on('change:block', this.updateBlock);
                this.on('change:disabled', this.updateDisabled);
                this.on('change:href', this.updateHref);
            },

            updateType() {
                const type = this.get('buttonType');
                const outline = this.get('outline');

                // Remove all button type classes
                options.buttonTypes.forEach(btn => {
                    this.removeClass(`btn-${btn.id}`);
                    this.removeClass(`btn-outline-${btn.id}`);
                });

                // Add new type class
                this.addClass(outline ? `btn-outline-${type}` : `btn-${type}`);
            },

            updateSize() {
                const size = this.get('buttonSize');

                // Remove all size classes
                options.buttonSizes.forEach(sz => {
                    if (sz.id) this.removeClass(sz.id);
                });

                // Add new size if specified
                if (size) this.addClass(size);
            },

            updateOutline() {
                const outline = this.get('outline');
                const type = this.get('buttonType');

                // Toggle outline class
                this.removeClass([`btn-${type}`, `btn-outline-${type}`]);
                this.addClass(outline ? `btn-outline-${type}` : `btn-${type}`);
            },

            updateBlock() {
                const block = this.get('block');
                block ? this.addClass('btn-block') : this.removeClass('btn-block');
            },

            updateDisabled() {
                const disabled = this.get('disabled');
                disabled ?
                    this.addClass('disabled').addAttributes({ 'aria-disabled': 'true' }) :
                    this.removeClass('disabled').removeAttributes('aria-disabled');
            },

            updateHref() {
                const href = this.get('href');
                href ?
                    this.addAttributes({ href }) :
                    this.addAttributes({ href: '#' });
            }
        },

        view: {
            events: {
                click: 'handleClick'
            },

            handleClick(e) {
                // Prevent default if button is disabled
                if (this.model.get('disabled')) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }
        }
    });

    // BUTTON GROUP COMPONENT (optional)
    editor.Components.addType("bs-button-group", {
        isComponent: el => el.classList?.contains('bs-btn-group'),

        model: {
            defaults: {
                tagName: 'div',
                name: 'Button group',
                droppable: 'a.btn', // Only allow buttons
                draggable: true,
                removable: true,
                copyable: true,
                attributes: { class: 'bs-btn-group btn-group', role: 'group' },
                traits: [
                    {
                        type: 'select',
                        label: 'Group Size',
                        name: 'groupSize',
                        options: options.buttonGroupSizes,
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Vertical Layout',
                        name: 'vertical',
                        changeProp: true
                    }
                ],
                components: [
                    {
                        type: 'bs-button',
                        attributes: { class: 'btn btn-primary' },
                        content: 'Button 1'
                    },
                    {
                        type: 'bs-button',
                        attributes: { class: 'btn btn-primary' },
                        content: 'Button 2'
                    },
                    {
                        type: 'bs-button',
                        attributes: { class: 'btn btn-primary' },
                        content: 'Button 3'
                    }
                ]
            },

            init() {
                this.on('change:groupSize', this.updateSize);
                this.on('change:vertical', this.updateLayout);
            },

            updateSize() {
                const size = this.get('groupSize');

                // Remove all size classes
                options.buttonGroupSizes.forEach(sz => {
                    if (sz.id) this.removeClass(sz.id);
                });

                // Add new size if specified
                if (size) this.addClass(size);
            },

            updateLayout() {
                const vertical = this.get('vertical');
                if (vertical) {
                    this.addClass('btn-group-vertical');
                    this.removeClass('btn-group');
                } else {
                    this.addClass('btn-group');
                    this.removeClass('btn-group-vertical');
                }
            }
        }
    });

    // Add buttons to blocks panel
    editor.Blocks.add("bs-button", {
        label: "Button",
        category: "Bootstrap Component",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M384 32C419.3 32 448 60.65 448 96V416C448 451.3 419.3 480 384 480H64C28.65 480 0 451.3 0 416V96C0 60.65 28.65 32 64 32H384zM384 80H64C55.16 80 48 87.16 48 96V416C48 424.8 55.16 432 64 432H384C392.8 432 400 424.8 400 416V96C400 87.16 392.8 80 384 80z"/></svg>`,
        content: {
            type: "bs-button",
            content: options.defaultText
        }
    });

    editor.Blocks.add("bs-button-group", {
        label: "Button Group",
        category: "Bootstrap Component",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M192 32C209.7 32 224 46.33 224 64V448C224 465.7 209.7 480 192 480H64C46.33 480 32 465.7 32 448V64C32 46.33 46.33 32 64 32H192zM384 32C401.7 32 416 46.33 416 64V448C416 465.7 401.7 480 384 480H256C238.3 480 224 465.7 224 448V64C224 46.33 238.3 32 256 32H384z"/></svg>`,
        content: { type: "bs-button-group" }
    });

});