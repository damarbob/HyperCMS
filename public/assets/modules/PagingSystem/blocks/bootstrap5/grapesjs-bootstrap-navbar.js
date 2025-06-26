grapesjs.plugins.add("grapesjs-bootstrap-navbar", function (editor, opts = {}) {
    const { Components, BlockManager } = editor;

    // Navbar Container Component
    Components.addType("bs-navbar", {
        isComponent: el => el.classList?.contains('navbar') && el.classList.contains('bs-navbar'),

        model: {
            defaults: {
                tagName: 'nav',
                name: 'Navbar',
                draggable: true,
                droppable: '.navbar-collapse, .navbar-nav, .dropdown-menu',
                attributes: {
                    class: 'navbar bs-navbar navbar-expand-lg',
                    'data-bs-theme': 'light'
                },
                traits: [
                    {
                        type: 'select',
                        label: 'Color Scheme',
                        name: 'theme',
                        options: [
                            { id: 'light', name: 'Light' },
                            { id: 'dark', name: 'Dark' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Container',
                        name: 'container',
                        options: [
                            { id: '', name: 'Normal' },
                            { id: 'container', name: 'Container' },
                            { id: 'container-fluid', name: 'Fluid' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Position',
                        name: 'position',
                        options: [
                            { id: '', name: 'Static' },
                            { id: 'fixed-top', name: 'Fixed Top' },
                            { id: 'fixed-bottom', name: 'Fixed Bottom' },
                            { id: 'sticky-top', name: 'Sticky Top' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Enable Search',
                        name: 'hasSearch',
                        changeProp: true
                    }
                ],
                components: [
                    {
                        tagName: 'div',
                        attributes: { class: 'container-fluid' },
                        name: 'Navbar Container',
                        components: [
                            {
                                type: 'link',
                                tagName: 'a',
                                attributes: { class: 'navbar-brand align-items-center', href: '#' },
                                name: 'Navbar Brand',
                                components: [
                                    {
                                        type: 'image',
                                        name: 'Navbar Logo',
                                        attributes: {
                                            src: 'https://getbootstrap.com/docs/5.3/assets/brand/bootstrap-logo.svg',
                                            alt: 'Bootstrap Logo',
                                            // width: 30,
                                            // height: 24,
                                            class: 'img-fluid d-inline-block align-text-top'
                                        },
                                        style: {
                                            // width: '30px',
                                            // height: '24px'
                                            'max-height': '40px'
                                        }
                                    },
                                    {
                                        type: 'text',
                                        name: 'Navbar Brand Text',
                                        classes: 'ms-2',
                                        tagName: 'span',
                                        content: 'Navbar'
                                    }
                                ],
                            },
                            {
                                tagName: 'button',
                                attributes: {
                                    class: 'navbar-toggler',
                                    type: 'button',
                                    'data-bs-toggle': 'collapse',
                                    'data-bs-target': '#navbarNav'
                                },
                                name: 'Navbar Toggler',
                                components: {
                                    tagName: 'span',
                                    attributes: { class: 'navbar-toggler-icon' },
                                    name: 'Toggler Icon'
                                }
                            },
                            {
                                tagName: 'div',
                                attributes: {
                                    class: 'collapse navbar-collapse',
                                    id: 'navbarNav'
                                },
                                name: 'Navbar Collapse',
                                components: [
                                    {
                                        tagName: 'ul',
                                        attributes: { class: 'navbar-nav me-auto mb-2 mb-lg-0' },
                                        name: 'Navbar Nav',
                                        components: [
                                            {
                                                type: 'bs-nav-item',
                                            },
                                            {
                                                tagName: 'form',
                                                attributes: { class: 'd-flex' },
                                                name: 'Navbar Search Form',
                                                role: 'search',
                                                components: [
                                                    {
                                                        tagName: 'input',
                                                        attributes: {
                                                            class: 'form-control me-2',
                                                            type: 'search',
                                                            placeholder: 'Search'
                                                        },
                                                        name: 'Seach Input',
                                                    },
                                                    {
                                                        tagName: 'button',
                                                        attributes: {
                                                            class: 'btn btn-outline-success',
                                                            type: 'submit'
                                                        },
                                                        name: 'Search Button',
                                                        components: [
                                                            {
                                                                type: 'text',
                                                                content: 'Search',
                                                                name: 'Search Button Text'
                                                            }
                                                        ]
                                                    }
                                                ]
                                            }
                                        ]
                                    },
                                ]
                            }
                        ]
                    }
                ],
                theme: 'light',
                position: '',
                hasSearch: true,
            },

            init() {
                this.on('change:theme', this.updateTheme);
                this.on('change:container', this.updateContainer);
                this.on('change:position', this.updatePosition);
                this.on('change:hasSearch', this.toggleSearch);
            },

            updateTheme() {
                const theme = this.get('theme');
                this.setAttributes({ 'data-bs-theme': theme });
                this.removeClass('bg-light bg-dark');
                this.addClass(`bg-${theme}`);
            },

            updateContainer() {
                const container = this.get('container');
                const containerEl = this.components().at(0);
                containerEl?.addAttributes({ class: container });
            },

            updatePosition() {
                const position = this.get('position');
                this.removeClass('fixed-top fixed-bottom sticky-top');
                if (position) this.addClass(position);
            },

            toggleSearch() {
                const hasSearch = this.get('hasSearch');
                const form = this.find('form')[0];
                if (hasSearch) {
                    form.removeClass('d-none');
                    form.addClass('d-flex');
                } else {
                    form.removeClass('d-flex');
                    form.addClass('d-none');
                }
            }
        }
    });

    // Nav Item Component
    Components.addType("bs-nav-item", {
        isComponent: el => el.classList?.contains('nav-item'),

        model: {
            defaults: {
                tagName: 'li',
                name: 'Nav Item',
                draggable: '.navbar-nav, .dropdown-menu',
                droppable: false,
                attributes: { class: 'nav-item' },
                traits: [

                    {
                        type: 'text',
                        label: 'URL',
                        name: 'href',
                        placeholder: '#',
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Active',
                        name: 'active',
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Disabled',
                        name: 'disabled',
                        changeProp: true
                    }
                ],
                components: {
                    tagName: 'a',
                    name: 'Nav Link',
                    attributes: {
                        class: 'nav-link',
                        href: '#'
                    },
                    components: [
                        {
                            type: 'text',
                            tagName: 'span',
                            content: 'Nav Item',
                            name: 'Nav Item Text'
                        }
                    ]
                },
                href: '#',
                active: false,
                disabled: false
            },

            init() {
                this.on('change:href', this.updateHref);
                this.on('change:active', this.updateActive);
                this.on('change:disabled', this.updateDisabled);
            },

            updateHref() {
                const href = this.get('href');
                this.find('a')[0].addAttributes({ href });
            },

            updateActive() {
                const active = this.get('active');
                const link = this.find('a')[0];
                active ? link.addClass('active') : link.removeClass('active');
            },

            updateDisabled() {
                const disabled = this.get('disabled');
                const link = this.find('a')[0];
                if (disabled) {
                    link.addClass('disabled');
                    link.setAttributes({ tabindex: '-1', 'aria-disabled': 'true' });
                } else {
                    link.removeClass('disabled');
                    link.setAttributes({ tabindex: '0', 'aria-disabled': 'false' });
                }
            }
        }
    });

    // Dropdown Component
    Components.addType("bs-nav-dropdown", {
        isComponent: el => el.classList?.contains('nav-item') && el.querySelector('.dropdown-toggle'),

        model: {
            defaults: {
                tagName: 'li',
                name: 'Navbar Dropdown',
                draggable: '.navbar-nav, .dropdown-menu',
                droppable: false,
                attributes: { class: 'nav-item dropdown' },
                traits: [
                    {
                        type: 'text',
                        label: 'Dropdown Title',
                        name: 'title'
                    },
                    {
                        type: 'select',
                        label: 'Alignment',
                        name: 'alignment',
                        options: [
                            { id: '', name: 'Left' },
                            { id: 'dropdown-menu-end', name: 'Right' }
                        ],
                        changeProp: true
                    },

                ],
                components: [
                    {
                        tagName: 'a',
                        name: 'Dropdown Toggle',
                        attributes: {
                            class: 'nav-link dropdown-toggle',
                            href: '#',
                            role: 'button',
                            'data-bs-toggle': 'dropdown'
                        },
                        components: 'Dropdown'
                    },
                    {
                        tagName: 'ul',
                        name: 'Dropdown Menu',
                        attributes: { class: 'dropdown-menu' },
                        components: [
                            {
                                tagName: 'li',
                                name: 'Dropdown Item',
                                components: {
                                    tagName: 'a',
                                    name: 'Dropdown Item Link',
                                    attributes: { class: 'dropdown-item', href: '#' },
                                    components: 'Action'
                                }
                            }
                        ]
                    }
                ],
                alignment: '',
            },

            init() {
                this.on('change:title', this.updateTitle);
                this.on('change:alignment', this.updateAlignment);
            },

            updateTitle() {
                const title = this.get('title');
                this.components().at(0)?.set('components', title);
            },

            updateAlignment() {
                const alignment = this.get('alignment');
                const menu = this.find('.dropdown-menu')[0];
                menu?.removeClass('dropdown-menu-end');
                if (alignment) menu?.addClass(alignment);
            },
        }
    });

    // Add to Blocks Panel
    BlockManager.add("bs-navbar", {
        label: "Navbar",
        category: "Bootstrap Navbar",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M3 6h18v2H3V6m0 5h18v2H3v-2m0 5h18v2H3v-2Z"/></svg>`,
        content: { type: "bs-navbar" }
    });

    BlockManager.add("bs-nav-item", {
        label: "Nav Item",
        category: "Bootstrap Navbar",
        content: { type: "bs-nav-item" },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="circle" class="svg-inline--fa fa-circle fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z"></path></svg>`
    });

    BlockManager.add("bs-nav-dropdown", {
        label: "Dropdown",
        category: "Bootstrap Navbar",
        content: { type: "bs-nav-dropdown" },
        media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z" fill-rule="nonzero"></path>
    <polygon class="gjs-block-svg-path" transform="translate(18.500000, 12.000000) scale(1, -1) translate(-18.500000, -12.000000) " points="18.5 11 20 13 17 13"></polygon>
    <rect class="gjs-block-svg-path" x="4" y="11.5" width="11" height="1"></rect>
</svg>`
    });

    // Add JavaScript Interactions
    // editor.on('component:selected', component => {
    //     if (component.get('type') === 'bs-nav-dropdown') {
    //         component.find('.dropdown-menu')[0]?.set('attributes', {
    //             style: 'display: block; position: static'
    //         });
    //     }
    // });

    // editor.on('component:deselected', component => {
    //     if (component.get('type') === 'bs-nav-dropdown') {
    //         component.find('.dropdown-menu')[0]?.set('attributes', {
    //             style: ''
    //         });
    //     }
    // });
});