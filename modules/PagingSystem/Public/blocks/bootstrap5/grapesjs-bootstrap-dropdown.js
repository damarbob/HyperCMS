grapesjs.plugins.add('grapesjs-bootstrap-dropdown', function (editor, opts = {}) {
    const defaults = {
        buttonStyle: ['btn-primary', 'btn-secondary', 'btn-success', 'btn-danger', 'btn-warning', 'btn-info', 'btn-light', 'btn-dark'],
        ...opts
    };

    // 1. Dropdown main container
    editor.Components.addType('bs-dropdown', {
        isComponent: el => el.tagName === 'DIV' && (el.classList.contains('dropdown') || el.classList.contains('btn-group')),
        model: {
            defaults: {
                tagName: 'div',
                name: 'Dropdown',
                classes: ['btn-group', 'dropdown'],
                droppable: true,
                editable: true,
                textable: 1,
                traits: [
                    {
                        type: 'checkbox',
                        label: 'Show on hover',
                        name: 'showOnHover',
                        changeProp: true,
                    },
                ],
                components: [
                    {
                        type: 'bs-dropdown-button',
                    },
                    {
                        type: 'bs-dropdown-menu',
                    },
                ],
            },
            init() {
                this.on('change:showOnHover', () => {
                    const showOnHover = this.get('showOnHover');
                    if (showOnHover) {
                        this.addClass('show');
                    } else {
                        this.removeClass('show');
                    }
                });
                this.setAriaLabelledby();
            },
            setAriaLabelledby() {
                const button = this.findFirstType('bs-dropdown-button');
                if (button) {
                    const buttonId = button.get('attributes').id;
                    this.findFirstType('bs-dropdown-menu').addAttributes({ 'aria-labelledby': buttonId });
                }
            },
        },
    });

    // 2. Dropdown button
    editor.Components.addType('bs-dropdown-button', {
        isComponent: el => el.tagName === 'BUTTON' && el.classList.contains('dropdown-toggle'),
        model: {
            defaults: {
                tagName: 'button',
                name: 'Dropdown button',
                classes: ['btn', 'dropdown-toggle'],
                attributes: { type: 'button', 'data-bs-offset': '0,30', 'data-bs-reference': 'parent', 'data-bs-toggle': 'dropdown', 'aria-expanded': 'false', id: 'dropdownButton-' + Math.random().toString(36).substr(2, 9) },
                editable: true,
                draggable: false,
                textable: 1,
                traits: [
                    {
                        type: 'select',
                        label: 'Button Style',
                        name: 'buttonStyle',
                        options: [
                            {
                                value: '',
                                name: 'Default'
                            },
                            ...defaults.buttonStyle.map(style => ({ value: style, name: style.replace('btn-', '') }))],
                        changeProp: true,
                    },
                ],
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'dropdown'
                    }
                ],
                buttonStyle: '',
            },
            init() {
                this.on('change:buttonStyle', this.updateButtonStyle());
            },
            updateButtonStyle() {
                const style = this.get('buttonStyle');
                if (style) {
                    this.removeClass(defaults.buttonStyle.join(' '));
                    this.addClass(style);
                }

            }

        },
    });

    // 3. Dropdown menu
    editor.Components.addType('bs-dropdown-menu', {
        isComponent: el => el.tagName === 'UL' && el.classList.contains('dropdown-menu'),
        model: {
            defaults: {
                tagName: 'ul',
                name: 'Dropdown menu',
                classes: ['dropdown-menu'],
                attributes: { 'data-bs-display': 'static' },
                editable: true,
                components: [
                    {
                        type: 'bs-dropdown-item',
                    }
                ]
            },

        }
    });

    // 4. dropdown item
    editor.Components.addType('bs-dropdown-item', {
        isComponent: el => el.tagName === 'LI' && el.classList.contains('bs-dropdown-item'),
        model: {
            defaults: {
                tagName: 'li',
                name: 'Dropdown item',
                classes: ['bs-dropdown-item'],
                draggable: '.dropdown-menu',
                editable: true,
                textable: 1,
                components: [
                    {
                        type: 'bs-dropdown-item-link',
                    },
                ],
            },
        },
    });

    // 4. dropdown item (active)
    editor.Components.addType('bs-dropdown-item-active', {
        isComponent: el => el.tagName === 'LI' && el.classList.contains('bs-dropdown-item-active'),
        model: {
            defaults: {
                tagName: 'li',
                name: 'Dropdown item active',
                classes: ['bs-dropdown-item-active'],
                editable: true,
                draggable: '.dropdown-menu',
                textable: 1,
                components: [
                    {
                        type: 'bs-dropdown-item-link-active',
                    },
                ],
            },
        },
    });

    // 5. dropdown item link
    editor.Components.addType('bs-dropdown-item-link', {
        isComponent: el => el.tagName === 'A' && el.classList.contains('dropdown-item'),
        model: {
            defaults: {
                tagName: 'a',
                name: 'Dropdown item link',
                classes: ['dropdown-item'],
                draggable: '.bs-dropdown-item',
                attributes: { href: '#' },
                editable: true,
                textable: 1,
                traits: [
                    {
                        type: 'text',
                        label: 'Link',
                        name: 'href',
                    },
                ],
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'dropdown item'
                    }
                ],
            }
        }
    });

    // 5. dropdown item link (active)
    editor.Components.addType('bs-dropdown-item-link-active', {
        isComponent: el => el.tagName === 'A' && el.classList.contains('active') && el.classList.contains('dropdown-item'),
        model: {
            defaults: {
                tagName: 'a',
                name: 'Dropdown item link active',
                classes: ['dropdown-item', 'active'],
                draggable: '.bs-dropdown-item',
                attributes: { href: '#' },
                editable: true,
                textable: 1,
                traits: [
                    {
                        type: 'text',
                        label: 'Link',
                        name: 'href',
                    },
                ],
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'dropdown item'
                    }
                ],
            }
        }
    });

    // additional: dropdown header
    editor.Components.addType('bs-dropdown-header', {
        isComponent: el => el.tagName === 'LI' && el.classList.contains('bs-dropdown-header'),
        model: {
            defaults: {
                tagName: 'li',
                name: 'Dropdown header',
                classes: ['bs-dropdown-header'],
                droppable: false,
                editable: false,
                components: [
                    {
                        type: 'text',
                        tagName: 'h6',
                        classes: ['dropdown-header'],
                        content: 'Dropdown header',
                    },
                ],
            },
        },
    });

    // additional: dropdown divider
    editor.Components.addType('bs-dropdown-divider', {
        isComponent: el => el.tagName === 'LI' && el.classList.contains('bs-dropdown-divider'),
        model: {
            defaults: {
                tagName: 'li',
                name: 'Dropdown divider',
                classes: ['bs-dropdown-divider'],
                droppable: false,
                editable: false,
                textable: 0,
                content: `<hr class="dropdown-divider">`,
            },
        },
    });

    editor.Blocks.add('bs-dropdown', {
        label: 'Dropdown Container',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown'
        },
        media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z" fill-rule="nonzero"></path>
    <polygon class="gjs-block-svg-path" transform="translate(18.500000, 12.000000) scale(1, -1) translate(-18.500000, -12.000000) " points="18.5 11 20 13 17 13"></polygon>
    <rect class="gjs-block-svg-path" x="4" y="11.5" width="11" height="1"></rect>
</svg>`
    });
    editor.Blocks.add('bs-dropdown-button', {
        label: 'Dropdown Button',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown-button'
        },
        media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z" fill-rule="nonzero"></path>
    <rect class="gjs-block-svg-path" x="4" y="11.5" width="16" height="1"></rect>
</svg>`
    });
    editor.Blocks.add('bs-dropdown-menu', {
        label: 'Dropdown Menu',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown-menu'
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="ellipsis-h" class="svg-inline--fa fa-ellipsis-h fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M328 256c0 39.8-32.2 72-72 72s-72-32.2-72-72 32.2-72 72-72 72 32.2 72 72zm104-72c-39.8 0-72 32.2-72 72s32.2 72 72 72 72-32.2 72-72-32.2-72-72-72zm-352 0c-39.8 0-72 32.2-72 72s32.2 72 72 72 72-32.2 72-72-32.2-72-72-72z"></path></svg>`
    });
    editor.Blocks.add('bs-dropdown-item', {
        label: 'Dropdown Item',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown-item'
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="circle" class="svg-inline--fa fa-circle fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z"></path></svg>`
    });
    editor.Blocks.add('bs-dropdown-item-active', {
        label: 'Dropdown Item Active',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown-item-active'
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="check-square" class="svg-inline--fa fa-check-square fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M400 480H48c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h352c26.51 0 48 21.49 48 48v352c0 26.51-21.49 48-48 48zm-204.686-98.059l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.248-16.379-6.249-22.628 0L184 302.745l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.25 16.379 6.25 22.628.001z"></path></svg>`
    });
    editor.Blocks.add('bs-dropdown-header', {
        label: 'Dropdown Header',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown-header'
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="heading" class="svg-inline--fa fa-heading fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M32 64c0-17.67 14.33-32 32-32h128c17.67 0 32 14.33 32 32v64H32V64zm0 128h160v64H32v-64zm0 128h160v64H32v-64zm0 128h160v64H32v-64zm224-384h128c17.67 0 32 14.33 32 32v64H256V64zm0 128h128v64H256v-64zm0 128h128v64H256v-64zm0 128h128v64H256v-64z"></path></svg>`
    });
    editor.Blocks.add('bs-dropdown-divider', {
        label: 'Dropdown Divider',
        category: 'Dropdown',
        content: {
            type: 'bs-dropdown-divider'
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="equals" class="svg-inline--fa fa-equals fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M416 304H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h384c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32zm0-192H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h384c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"></path></svg>`
    });
});
