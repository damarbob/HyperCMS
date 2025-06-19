grapesjs.plugins.add('grapesjs-bootstrap-breadcrumb', function (editor, opts = {}) {
    const defaults = { classes: ['breadcrumb', 'breadcrumb-item'], ...opts };

    editor.Components.addType('bs-breadcrumb-item-active', {
        isComponent: el => el.tagName === 'LI' && el.classList.contains('breadcrumb-item') && el.classList.contains('active'),
        model: {
            defaults: {
                tagName: 'li',
                name: 'Breadcrumb item active',
                classes: ['breadcrumb-item', 'active'],
                attributes: { 'aria-current': 'page' },
                droppable: true,
                editable: true,
                textable: 1,
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'Breadcrumb Item',
                    },
                ],
            },
        },
    });

    editor.Components.addType('bs-breadcrumb-item', {
        isComponent: el => el.tagName === 'LI' && el.classList.contains('breadcrumb-item'),
        model: {
            defaults: {
                tagName: 'li',
                name: 'Breadcrumb item',
                classes: ['breadcrumb-item'],
                droppable: true,
                editable: true,
                textable: 1,
                components: [
                    {
                        type: 'text',
                        tagName: 'a',
                        content: 'Breadcrumb Item',
                        attributes: { href: '#' },
                        traits: [
                            {
                                type: 'text',
                                label: 'Link',
                                name: 'href',
                            },
                        ],
                    },
                ],
            },
        },
    });

    editor.Components.addType('bs-breadcrumb', {
        isComponent: el => el.tagName === 'OL' && el.classList.contains('breadcrumb'),
        model: {
            defaults: {
                tagName: 'ol',
                name: 'Breadcrumb',
                classes: ['breadcrumb'],
                droppable: true,
                editable: true,
                textable: 1,
                components: [
                    {
                        type: 'bs-breadcrumb-item',
                    },
                    {
                        type: 'bs-breadcrumb-item-active',
                    },
                ],
            },
        },
    });

    editor.Components.addType('bs-breadcrumb-nav', {
        isComponent: el => el.tagName === 'NAV' && el.classList.contains('bs-breadcrumb-nav'),
        model: {
            defaults: {
                tagName: 'nav',
                name: 'Breadcrumb nav',
                classes: ['bs-breadcrumb-nav'],
                attributes: { 'aria-label': 'breadcrumb' },
                droppable: true,
                editable: true,
                textable: 1,
                components: [
                    {
                        type: 'bs-breadcrumb',
                    },
                ],
            },
        },
    });

    editor.Blocks.add('bs-breadcrumb-nav', {
        label: 'Breadcrumb',
        category: 'Navigation',
        content: {
            type: 'bs-breadcrumb-nav',
        },
        media: '<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="link" class="svg-inline--fa fa-link fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M326.612 185.391c59.747 59.809 58.927 155.698.36 214.59-.11.12-.24.25-.36.37l-67.2 67.2c-59.27 59.27-155.699 59.262-214.96 0-59.27-59.26-59.27-155.7 0-214.96l37.106-37.106c9.84-9.84 26.786-3.3 27.294 10.606.648 17.722 3.826 35.527 9.69 52.721 1.986 5.822.567 12.262-3.783 16.612l-13.087 13.087c-28.026 28.026-28.905 73.66-1.155 101.96 28.024 28.579 74.086 28.749 102.325.51l67.2-67.19c28.191-28.191 28.073-73.757 0-101.83-3.701-3.694-7.429-6.564-10.341-8.569a16.037 16.037 0 0 1-6.947-12.606c-.396-10.567 3.348-21.456 11.698-29.806l21.054-21.055c5.521-5.521 14.182-6.199 20.584-1.731a152.482 152.482 0 0 1 20.522 17.197zM467.547 44.449c-59.261-59.262-155.69-59.27-214.96 0l-67.2 67.2c-.12.12-.25.25-.36.37-58.566 58.892-59.387 154.781.36 214.59a152.454 152.454 0 0 0 20.521 17.196c6.402 4.468 15.064 3.789 20.584-1.731l21.054-21.055c8.35-8.35 12.094-19.239 11.698-29.806a16.037 16.037 0 0 0-6.947-12.606c-2.912-2.005-6.64-4.875-10.341-8.569-28.073-28.073-28.191-73.639 0-101.83l67.2-67.19c28.239-28.239 74.3-28.069 102.325.51 27.75 28.3 26.872 73.934-1.155 101.96l-13.087 13.087c-4.35 4.35-5.769 10.79-3.783 16.612 5.864 17.194 9.042 34.999 9.69 52.721.509 13.906 17.454 20.446 27.294 10.606l37.106-37.106c59.271-59.259 59.271-155.699.001-214.959z"></path></svg>'
    });

    editor.Blocks.add('bs-breadcrumb-item', {
        label: 'Breadcrumb Item',
        category: 'Navigation',
        content: {
            type: 'bs-breadcrumb-item',
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="circle" class="svg-inline--fa fa-circle fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8z"></path></svg>`
    });

    editor.Blocks.add('bs-breadcrumb-item-active', {
        label: 'Breadcrumb Item Active',
        category: 'Navigation',
        content: {
            type: 'bs-breadcrumb-item-active',
        },
        media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="check-square" class="svg-inline--fa fa-check-square fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M400 480H48c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h352c26.51 0 48 21.49 48 48v352c0 26.51-21.49 48-48 48zm-204.686-98.059l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.248-16.379-6.249-22.628 0L184 302.745l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.25 16.379 6.25 22.628.001z"></path></svg>`
    });
});