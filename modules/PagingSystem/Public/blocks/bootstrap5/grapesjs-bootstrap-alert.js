grapesjs.plugins.add('grapesjs-bootstrap-alert', function (editor, opts = {}) {
    const defaults = { classes: ['alert', 'alert-primary', 'alert-secondary', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info', 'alert-light', 'alert-dark'], ...opts };

    editor.Components.addType('bs-alert', {
        isComponent: el => el.tagName === 'DIV' && el.classList.contains('bs-alert'),
        model: {
            defaults: {
                type: 'text',
                tagName: 'div',
                name: 'Alert',
                classes: ['bs-alert', 'alert', 'alert-primary'],
                attributes: { role: 'alert' },
                droppable: true,
                editable: true,
                textable: 1,
                traits: [
                    {
                        type: 'select',
                        label: 'Type',
                        name: 'alertType',
                        options: defaults.classes.map(cls => ({ value: cls, name: cls.replace('alert-', '') })),
                        changeProp: true,
                    },
                    {
                        type: 'checkbox',
                        label: 'Dismissible',
                        name: 'alertDismissible',
                        changeProp: true,
                    },
                ],
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'Alert message',
                    },
                ],
                alertType: 'alert-primary',
                alertDismissible: false,
            },
            init() {
                this.on('change:alertType', () => {
                    const type = this.get('alertType');
                    this.removeClass(defaults.classes.join(' '));
                    this.addClass(['alert', type]);
                });
                this.on('change:alertDismissible', () => {
                    const dismissible = this.get('alertDismissible');
                    if (dismissible) {
                        this.addClass('alert-dismissible');
                        this.append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
                    } else {
                        this.removeClass('alert-dismissible');
                        this.find('.close')[0].remove();
                    }
                });
            },
        },
    });

    editor.Components.addType('bs-alert-link', {
        isComponent: el => el.tagName === 'A' && el.classList.contains('bs-alert-link'),
        model: {
            defaults: {
                tagName: 'a',
                name: 'Alert link',
                classes: ['bs-alert-link', 'alert-link'],
                droppable: false,
                // draggable: '.alert',
                attributes: { href: '#' },
                editable: true,
                traits: [
                    {
                        type: 'text',
                        label: 'Link',
                        name: 'href',
                    }
                ],
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'Alert Link',
                    },
                ],
            },
        },
    });

    editor.Components.addType('bs-alert-heading', {
        isComponent: el => el.tagName === 'H4' && el.classList.contains('bs-alert-heading'),
        model: {
            defaults: {
                tagName: 'h4',
                name: 'Alert heading',
                classes: ['bs-alert-heading', 'alert-heading'],
                droppable: false,
                draggable: '.alert',
                editable: true,
                components: [
                    {
                        type: 'text',
                        tagName: 'span',
                        content: 'Alert Heading',
                    },
                ],
            },
        },
    });

    editor.Blocks.add('bs-alert', {
        label: 'Alert',
        category: 'Alerts',
        content: {
            type: 'bs-alert',
        },
        media: '<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="exclamation-triangle" class="svg-inline--fa fa-exclamation-triangle fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M569.517 440.013C587.975 472.007 564.806 512 527.94 512H48.054c-36.937 0-59.999-40.055-41.577-71.987L246.423 23.985c18.467-32.009 64.72-31.951 83.154 0l239.94 416.028zM288 354c-25.405 0-46 20.595-46 46s20.595 46 46 46 46-20.595 46-46-20.595-46-46-46zm-43.673-165.346l7.418 136c.347 6.364 5.609 11.346 11.982 11.346h48.546c6.373 0 11.635-4.982 11.982-11.346l7.418-136c.375-6.874-5.098-12.654-11.982-12.654h-63.383c-6.884 0-12.356 5.78-11.981 12.654z"></path></svg>',
    });
    editor.Blocks.add('bs-alert-link', {
        label: 'Alert Link',
        category: 'Alerts',
        content: {
            type: 'bs-alert-link',
        },
        media: '<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="link" class="svg-inline--fa fa-link fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M326.612 185.391c59.747 59.809 58.927 155.698.36 214.59-.11.12-.24.25-.36.37l-67.2 67.2c-59.27 59.27-155.699 59.262-214.96 0-59.27-59.26-59.27-155.7 0-214.96l37.106-37.106c9.84-9.84 26.786-3.3 27.294 10.606.648 17.722 3.826 35.527 9.69 52.721 1.986 5.822.567 12.262-3.783 16.612l-13.087 13.087c-28.026 28.026-28.905 73.66-1.155 101.96 28.024 28.579 74.086 28.749 102.325.51l67.2-67.19c28.191-28.191 28.073-73.757 0-101.83-3.701-3.694-7.429-6.564-10.341-8.569a16.037 16.037 0 0 1-6.947-12.606c-.396-10.567 3.348-21.456 11.698-29.806l21.054-21.055c5.521-5.521 14.182-6.199 20.584-1.731a152.482 152.482 0 0 1 20.522 17.197zM467.547 44.449c-59.261-59.262-155.69-59.27-214.96 0l-67.2 67.2c-.12.12-.25.25-.36.37-58.566 58.892-59.387 154.781.36 214.59a152.454 152.454 0 0 0 20.521 17.196c6.402 4.468 15.064 3.789 20.584-1.731l21.054-21.055c8.35-8.35 12.094-19.239 11.698-29.806a16.037 16.037 0 0 0-6.947-12.606c-2.912-2.005-6.64-4.875-10.341-8.569-28.073-28.073-28.191-73.639 0-101.83l67.2-67.19c28.239-28.239 74.3-28.069 102.325.51 27.75 28.3 26.872 73.934-1.155 101.96l-13.087 13.087c-4.35 4.35-5.769 10.79-3.783 16.612 5.864 17.194 9.042 34.999 9.69 52.721.509 13.906 17.454 20.446 27.294 10.606l37.106-37.106c59.271-59.259 59.271-155.699.001-214.959z"></path></svg>'
    });
    editor.Blocks.add('bs-alert-heading', {
        label: 'Alert Heading',
        category: 'Alerts',
        content: {
            type: 'bs-alert-heading',
        },
        media: '<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="heading" class="svg-inline--fa fa-heading fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M448 96v320h32a16 16 0 0 1 16 16v32a16 16 0 0 1-16 16H320a16 16 0 0 1-16-16v-32a16 16 0 0 1 16-16h32V288H160v128h32a16 16 0 0 1 16 16v32a16 16 0 0 1-16 16H32a16 16 0 0 1-16-16v-32a16 16 0 0 1 16-16h32V96H32a16 16 0 0 1-16-16V48a16 16 0 0 1 16-16h160a16 16 0 0 1 16 16v32a16 16 0 0 1-16 16h-32v128h192V96h-32a16 16 0 0 1-16-16V48a16 16 0 0 1 16-16h160a16 16 0 0 1 16 16v32a16 16 0 0 1-16 16z"></path></svg>'
    });
});