grapesjs.plugins.add("grapesjs-bootstrap-forms", function (editor, opts = {}) {
    const { Components, BlockManager } = editor;
    const config = editor.getConfig();
    const formControlClasses = ['form-control', 'form-control-lg', 'form-control-sm'];
    const baseUrl = config.baseUrl || '';

    // Form Container Component
    Components.addType("bs-form", {
        isComponent: el => el.tagName === 'FORM' && el.classList.contains('bs-form'),

        model: {
            defaults: {
                tagName: 'form',
                name: 'Form',
                draggable: true,
                droppable: true,
                attributes: {
                    class: 'bs-form',
                    method: 'post'
                },
                style: { 'min-height': '100px' },
                traits: [
                    {
                        type: 'select',
                        label: 'Layout',
                        name: 'formLayout',
                        options: [
                            { id: 'vertical', name: 'Vertical' },
                            { id: 'horizontal', name: 'Horizontal' },
                            { id: 'inline', name: 'Inline' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'text',
                        label: 'Action URL',
                        name: 'action',
                        placeholder: '/submit'
                    },
                    {
                        type: 'select',
                        label: 'Method',
                        name: 'method',
                        options: [
                            { id: 'get', name: 'GET' },
                            { id: 'post', name: 'POST' }
                        ]
                    },
                    {
                        type: 'select',
                        label: 'Enctype',
                        name: 'enctype',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'multipart/form-data', name: 'File Upload' }
                        ]
                    }
                ],
                components: []
            }
        }
    });

    // Common Input Traits
    const inputTraits = [
        {
            type: 'text',
            label: 'Name',
            name: 'name',
            placeholder: 'field-name'
        },
        {
            type: 'checkbox',
            label: 'Required',
            name: 'required'
        },
        {
            type: 'checkbox',
            label: 'Readonly',
            name: 'readonly'
        },
        {
            type: 'checkbox',
            label: 'Disabled',
            name: 'disabled'
        }
    ];

    // Input Component
    Components.addType("bs-input", {
        isComponent: el => el.tagName === 'INPUT' && (el.classList.contains('bs-input') || el.classList.contains('form-control')),

        model: {
            defaults: {
                tagName: 'input',
                name: 'Text input',
                draggable: true,
                droppable: false,
                attributes: {
                    type: 'text',
                    class: 'bs-input form-control',
                    placeholder: 'Enter text',
                    id: 'input-' + Math.random().toString(36).substr(2, 9) // Unique ID for the input
                },
                traits: [
                    {
                        type: 'text',
                        label: 'id',
                        name: 'id',
                    },
                    ...inputTraits,
                    {
                        type: 'text',
                        label: 'Placeholder',
                        name: 'placeholder'
                    },
                    {
                        type: 'select',
                        label: 'Size',
                        name: 'size',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'form-control-sm', name: 'Small' },
                            { id: 'form-control-lg', name: 'Large' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Type',
                        name: 'type',
                        options: [
                            { id: 'text', name: 'Text' },
                            { id: 'email', name: 'Email' },
                            { id: 'password', name: 'Password' },
                            { id: 'number', name: 'Number' },
                            { id: 'date', name: 'Date' },
                            { id: 'file', name: 'File' }
                        ]
                    },
                    {
                        type: 'text',
                        label: 'Pattern',
                        name: 'pattern',
                        placeholder: 'Regex pattern'
                    },
                    {
                        type: 'text',
                        label: 'aria-label',
                        name: 'aria-label',
                    },
                    {
                        type: 'text',
                        label: 'aria-describedby',
                        name: 'aria-describedby',
                    }
                ],
                size: 'form-control',
            },

            init() {
                this.on('change:size', this.updateSize);
                // this.on('change:pattern', this.updatePattern);
            },

            updateSize() {
                const size = this.get('size');
                formControlClasses.forEach(c => this.removeClass(c));
                this.addClass(['form-control', size]);
            },
        }
    });

    // TextArea Component
    Components.addType("bs-textarea", {
        isComponent: el => el.tagName === 'TEXTAREA' && el.classList.contains('bs-textarea'),

        model: {
            defaults: {
                tagName: 'textarea',
                name: 'Textarea',
                draggable: true,
                droppable: false,
                attributes: {
                    type: 'text',
                    class: 'bs-textarea form-control',
                    placeholder: 'Enter text',
                    rows: 3,
                    id: 'input-' + Math.random().toString(36).substr(2, 9) // Unique ID for the input
                },
                traits: [
                    {
                        type: 'text',
                        label: 'id',
                        name: 'id',
                    },
                    ...inputTraits,
                    {
                        type: 'text',
                        label: 'Placeholder',
                        name: 'placeholder'
                    },
                    {
                        type: 'text',
                        label: 'Rows',
                        name: 'rows',
                    },
                ],
            },
        }
    });

    Components.addType("bs-label", {
        isComponent: el => el.tagName === 'LABEL' && (el.classList.contains('bs-label') || el.classList.contains('form-label')),

        model: {
            defaults: {
                tagName: 'label',
                name: 'Label',
                draggable: true,
                droppable: false,
                attributes: {
                    class: 'bs-label form-label',
                },
                components: {
                    type: 'text',
                    tagName: 'span',
                    content: 'Label',
                    draggable: false,
                    droppable: false,
                },
                traits: [
                    {
                        type: 'text',
                        label: 'For',
                        name: 'for',
                        placeholder: 'input-id',
                    }
                ]
            }
        }
    });

    // Form control Component
    Components.addType("bs-form-control", {
        isComponent: el => el.tagName === 'DIV' && el.classList.contains('bs-form-control'),

        model: {
            defaults: {
                droppable: false,
                name: 'Form control',
                attributes: {
                    class: 'bs-form-control mb-3',
                },
                traits: [
                    ...inputTraits,
                    // {
                    //     type: 'text',
                    //     label: 'Validation Message',
                    //     name: 'title'
                    // },
                    {
                        type: 'checkbox',
                        label: 'Floating label (set placeholder on input tag first)',
                        name: 'floatingLabel',
                        changeProp: true
                    }
                ],
                components: [
                    {
                        type: 'bs-label',
                        draggable: false,
                    },
                    {
                        type: 'bs-input',
                        draggable: false,
                    },
                ],
                floatingLabel: false,
            },

            init() {
                this.initializeLabel();
                this.on('change:floatingLabel', this.updateFloatingLabel);
            },

            initializeLabel() {
                this.findFirstType('bs-label').addAttributes({ for: this.findFirstType('bs-input').get('attributes').id });
            },

            updateFloatingLabel() {

                const floatingLabel = this.get('floatingLabel');
                const label = this.findFirstType('bs-label');
                const input = this.findFirstType('bs-input');

                if (floatingLabel) {
                    this.addClass('form-floating');
                    this.removeClass('form-control');
                    input.move(this, { at: 0 })
                } else {
                    this.removeClass('form-floating');
                    this.addClass('form-control');
                    label.move(this, { at: 0 });
                }
            }
        },
    });

    // Form control Component
    Components.addType("bs-form-control-textarea", {
        isComponent: el => el.tagName === 'DIV' && el.classList.contains('bs-form-control-textarea'),

        model: {
            defaults: {
                droppable: false,
                name: 'Form control textarea',
                attributes: {
                    class: 'bs-form-control-textarea mb-3',
                },
                traits: [
                    ...inputTraits,
                    {
                        type: 'checkbox',
                        label: 'Floating label (set placeholder on input tag first)',
                        name: 'floatingLabel',
                        changeProp: true
                    }
                ],
                components: [
                    {
                        type: 'bs-label',
                        draggable: false,
                        attributes: {
                            class: 'form-label',
                        },
                    },
                    {
                        type: 'bs-textarea',
                        draggable: false,
                    },
                ],
                floatingLabel: false,
            },

            init() {
                this.initializeLabel();
                this.on('change:floatingLabel', this.updateFloatingLabel);
            },

            initializeLabel() {
                this.findFirstType('bs-label').addAttributes({ for: this.findFirstType('bs-textarea').get('attributes').id });
            },

            updateFloatingLabel() {

                const floatingLabel = this.get('floatingLabel');
                const label = this.findFirstType('bs-label');
                const textarea = this.findFirstType('bs-textarea');

                if (floatingLabel) {
                    this.addClass('form-floating');
                    this.removeClass('form-control');
                    textarea.move(this, { at: 0 })
                } else {
                    this.removeClass('form-floating');
                    this.addClass('form-control');
                    label.move(this, { at: 0 });
                }
            }
        },
    });

    // Select Component with Dynamic Options
    Components.addType("bs-select", {
        isComponent: el => el.tagName === 'SELECT' && el.classList.contains('form-select'),

        model: {
            defaults: {
                tagName: 'select',
                name: 'Form select',
                draggable: true,
                droppable: false,
                attributes: { class: 'form-select' },
                traits: [
                    {
                        type: 'text',
                        label: 'id',
                        name: 'id',
                    },
                    ...inputTraits,
                    {
                        type: 'select',
                        label: 'Size',
                        name: 'size',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'form-select-sm', name: 'Small' },
                            { id: 'form-select-lg', name: 'Large' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Data Source',
                        name: 'dataSource',
                        options: [
                            { id: 'manual', name: 'Manual' },
                            { id: 'dynamic', name: 'Dynamic' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'text',
                        label: 'Options (Manual)',
                        name: 'options',
                        placeholder: 'value:Label, value2:Label2',
                        visible: true,
                        changeProp: true,
                    },
                    {
                        type: 'text',
                        label: 'API URL (Dynamic)',
                        name: 'dataUrl',
                        placeholder: '/api/options',
                        visible: false
                    },
                    {
                        type: 'text',
                        label: 'Value Field',
                        name: 'valueField',
                        placeholder: 'id',
                        visible: false
                    },
                    {
                        type: 'text',
                        label: 'Label Field',
                        name: 'labelField',
                        placeholder: 'name',
                        visible: false
                    }
                ],
                dataSource: 'manual',
                options: [],

            },

            init() {
                this.on('change:dataSource', this.updateDataSource);
                this.on('change:options', this.updateOptions);
                this.on('change:dataUrl', this.fetchOptions);
            },

            updateDataSource() {
                const isManual = this.get('dataSource') === 'manual';
                this.setTraitVisibility('options', isManual);
                this.setTraitVisibility('dataUrl', !isManual);
                this.setTraitVisibility('valueField', !isManual);
                this.setTraitVisibility('labelField', !isManual);

                if (!isManual) this.fetchOptions();
            },

            setTraitVisibility(traitName, visible) {
                const trait = this.getTrait(traitName);
                if (trait) trait.set('visible', visible);
            },

            updateOptions() {
                const options = this.parseManualOptions(this.get('options'));
                this.components(options);
            },

            async fetchOptions() {
                const url = baseUrl + this.get('dataUrl');
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    const options = data.map(item => ({
                        tagName: 'option',
                        name: 'Option',
                        attributes: {
                            value: item[this.get('valueField')],
                            selected: item.selected ? 'selected' : ''
                        },
                        components: item[this.get('labelField')]
                    }));
                    this.components(options);
                } catch (error) {
                    // console.error('Error fetching options:', error);
                }
            },

            parseManualOptions(optionsStr) {
                return optionsStr.split(',').map(opt => {
                    const [value, label] = opt.split(':').map(s => s.trim());
                    return {
                        tagName: 'option',
                        name: 'Option',
                        attributes: { value },
                        components: label || value
                    };
                });
            }
        }
    });

    // Checkbox Component
    Components.addType("bs-checkbox", {
        isComponent: el => el.tagName === 'INPUT' && el.type === 'checkbox' && el.classList.contains('form-check-input'),

        model: {
            defaults: {
                tagName: 'input',
                name: 'Form checkbox input',
                draggable: true,
                droppable: false,
                attributes: {
                    type: 'checkbox',
                    class: 'form-check-input',
                    id: 'input-' + Math.random().toString(36).substr(2, 9), // Unique ID for the input
                    value: '',
                },
                traits: [
                    ...inputTraits,
                    {
                        type: 'text',
                        label: 'Value',
                        name: 'value',
                        placeholder: 'Checkbox value'
                    },
                ],
            },
        }
    });

    // Radio Component
    Components.addType("bs-radio", {
        isComponent: el => el.tagName === 'INPUT' && el.type === 'radio' && el.classList.contains('form-check-input'),

        model: {
            defaults: {
                tagName: 'input',
                name: 'Form radio input',
                draggable: true,
                droppable: false,
                attributes: {
                    type: 'radio',
                    class: 'form-check-input',
                    id: 'input-' + Math.random().toString(36).substr(2, 9), // Unique ID for the input
                },
                traits: [
                    ...inputTraits,
                    {
                        type: 'text',
                        label: 'Value',
                        name: 'value',
                        placeholder: 'Radio value'
                    },
                ],
            }
        }
    });

    // Radio & Checkbox Group Component
    const createChoiceGroup = (type) => ({
        isComponent: el => el.tagName === 'DIV' && (el.classList?.contains(`bs-${type}-group`) || el.classList?.contains('form-check')),

        model: {
            defaults: {
                tagName: 'div',
                name: 'Form check',
                draggable: true,
                droppable: false,
                attributes: { class: `bs-${type}-group form-check` },
                traits: [
                    {
                        type: 'select',
                        label: 'Data Source',
                        name: 'dataSource',
                        options: [
                            { id: 'manual', name: 'Manual' },
                            { id: 'dynamic', name: 'Dynamic' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'text',
                        label: 'API URL (Dynamic)',
                        name: 'dataUrl',
                        placeholder: '/api/options',
                        visible: false
                    },
                    {
                        type: 'checkbox',
                        label: 'Inline',
                        name: 'inline'
                    }
                ],
                components: [
                    {
                        type: `bs-${type}`,
                        draggable: false,
                    },
                    {
                        type: 'bs-label',
                        draggable: false,
                        attributes: {
                            class: 'form-check-label',
                        },
                    }
                ],
                dataSource: 'manual',
            },

            init() {
                this.on('change:dataSource', this.updateDataSource);
                this.on('change:dataUrl', this.fetchOptions);
                this.initializeLabel();
            },

            initializeLabel() {
                this.findFirstType('bs-label').addAttributes({ for: this.findFirstType(`bs-${type}`).get('attributes').id });
            },

            updateDataSource() {
                const isManual = this.get('dataSource') === 'manual';
                this.setTraitVisibility('dataUrl', !isManual);
                this.setTraitVisibility('valueField', !isManual);
                this.setTraitVisibility('labelField', !isManual);

                if (!isManual) this.fetchOptions();
            },

            setTraitVisibility(traitName, visible) {
                const trait = this.getTrait(traitName);
                if (trait) trait.set('visible', visible);
            },

            async fetchOptions() {
                const url = baseUrl + this.get('dataUrl');
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    const labelAttributes = this.findFirstType('label').getAttributes();
                    const inputAttributes = this.findFirstType(`bs-${type}`).getAttributes();
                    const options = data.map(item => ({
                        type: `bs-${type}-group`,
                        attributes: this.getAttributes(),
                        components: [
                            {
                                type: `bs-${type}`,
                                draggable: false,
                                attributes: {
                                    ...inputAttributes,
                                    value: item[this.get('valueField')],
                                }
                            },
                            {
                                type: 'bs-label',
                                draggable: false,
                                attributes: {
                                    ...labelAttributes,
                                    class: 'form-check-label',
                                },
                                content: item[this.get('labelField')]
                            }
                        ],
                    }));
                    editor.addComponents(options);
                } catch (error) {
                    // console.error('Error fetching options:', error);
                }
            },
        }
    });

    // Add Radio and Checkbox Groups
    Components.addType("bs-radio-group", createChoiceGroup('radio'));
    Components.addType("bs-checkbox-group", createChoiceGroup('checkbox'));

    // Input Group Component
    Components.addType("bs-input-group", {
        isComponent: el => el.tagName === 'DIV' && el.classList.contains('input-group'),

        model: {
            defaults: {
                tagName: 'div',
                name: 'Input group',
                draggable: true,
                droppable: true,
                attributes: { class: 'input-group mb-3' },
                components: `
                    <span class="input-group-text" id="basic-addon1">@</span>
                    <input type="text" class="form-control" placeholder="Username" aria-label="Username" aria-describedby="basic-addon1">
                `,
                traits: [
                    {
                        type: 'select',
                        label: 'Size',
                        name: 'size',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'input-group-sm', name: 'Small' },
                            { id: 'input-group-lg', name: 'Large' }
                        ],
                        changeProp: true
                    },
                ],
                size: 'input-group',
            },

            init() {

                this.on('change:size', this.updateSize);
            },

            updateSize() {
                const groupSize = this.get('size');
                this.removeClass(['input-group-sm', 'input-group-lg']);

                this.addClass(groupSize);
                // if (size) {
                // }
            }
        }
    });

    // Actions button Component
    Components.addType("bs-form-action-button", {
        isComponent: el => el.tagName === 'BUTTON' && el.classList.contains('bs-form-action-button'),

        model: {
            defaults: {
                tagName: 'button',
                name: 'Form action button',
                draggable: true,
                droppable: false,
                attributes: {
                    type: 'button',
                    class: 'btn btn-primary',
                    id: 'input-' + Math.random().toString(36).substr(2, 9) // Unique ID for the input
                },
                components: {
                    type: 'text',
                    tagName: 'span',
                },
                traits: [
                    {
                        type: 'text',
                        label: 'id',
                        name: 'id',
                    },
                    {
                        type: 'select',
                        label: 'Button Action Type',
                        name: 'type',
                        options: [
                            { id: 'button', name: 'Button' },
                            { id: 'submit', name: 'Submit' },
                            { id: 'reset', name: 'Reset' }
                        ],
                    },
                    {
                        type: 'select',
                        label: 'Size',
                        name: 'size',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'btn-sm', name: 'Small' },
                            { id: 'btn-lg', name: 'Large' }
                        ],
                        changeProp: true
                    },
                ],
                size: '',
            },
            init() {
                this.on('change:size', this.updateSize);
            },

            updateSize() {
                const size = this.get('size');
                this.removeClass(['btn-sm', 'btn-lg']);
                this.addClass(size);
            }
        }
    });

    // Form Validation Component
    Components.addType("bs-validation", {
        isComponent: el => el.classList?.contains('bs-validation'),

        model: {
            defaults: {
                tagName: 'div',
                name: 'Form validation',
                draggable: '.form-group',
                droppable: false,
                attributes: { class: 'invalid-feedback' },
                traits: [
                    {
                        type: 'select',
                        label: 'Validation Type',
                        name: 'type',
                        options: [
                            { id: 'invalid', name: 'Invalid Feedback' },
                            { id: 'valid', name: 'Valid Feedback' }
                        ]
                    },
                    {
                        type: 'text',
                        label: 'Message',
                        name: 'message',
                        placeholder: 'Validation message'
                    }
                ]
            }
        }
    });

    // Add Form Components to Blocks Panel
    const formBlocks = [
        {
            id: 'bs-form', label: 'Form Container', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,5.5 C22,5.2 21.5,5 20.75,5 L3.25,5 C2.5,5 2,5.2 2,5.5 L2,8.5 C2,8.8 2.5,9 3.25,9 L20.75,9 C21.5,9 22,8.8 22,8.5 L22,5.5 Z M21,8 L3,8 L3,6 L21,6 L21,8 Z" fill-rule="nonzero"></path>
    <path class="gjs-block-svg-path" d="M22,10.5 C22,10.2 21.5,10 20.75,10 L3.25,10 C2.5,10 2,10.2 2,10.5 L2,13.5 C2,13.8 2.5,14 3.25,14 L20.75,14 C21.5,14 22,13.8 22,13.5 L22,10.5 Z M21,13 L3,13 L3,11 L21,11 L21,13 Z" fill-rule="nonzero"></path>
    <rect class="gjs-block-svg-path" x="2" y="15" width="10" height="3" rx="0.5"></rect>
</svg>` },
        {
            id: 'bs-form-control', label: 'Text Input', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z"></path>
    <polygon class="gjs-block-svg-path" points="4 10 5 10 5 14 4 14"></polygon>
</svg>
` },
        {
            id: 'bs-form-control-textarea', label: 'TextArea', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,7.5 C22,6.6 21.5,6 20.75,6 L3.25,6 C2.5,6 2,6.6 2,7.5 L2,16.5 C2,17.4 2.5,18 3.25,18 L20.75,18 C21.5,18 22,17.4 22,16.5 L22,7.5 Z M21,17 L3,17 L3,7 L21,7 L21,17 Z"></path>
    <polygon class="gjs-block-svg-path" points="4 8 5 8 5 12 4 12"></polygon>
    <polygon class="gjs-block-svg-path" points="19 7 20 7 20 17 19 17"></polygon>
    <polygon class="gjs-block-svg-path" points="20 8 21 8 21 9 20 9"></polygon>
    <polygon class="gjs-block-svg-path" points="20 15 21 15 21 16 20 16"></polygon>
</svg>` },
        {
            id: 'bs-select', label: 'Select', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z" fill-rule="nonzero"></path>
    <polygon class="gjs-block-svg-path" transform="translate(18.500000, 12.000000) scale(1, -1) translate(-18.500000, -12.000000) " points="18.5 11 20 13 17 13"></polygon>
    <rect class="gjs-block-svg-path" x="4" y="11.5" width="11" height="1"></rect>
</svg>` },
        { id: 'bs-radio-group', label: 'Radio Group', media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="far" data-icon="dot-circle" class="svg-inline--fa fa-dot-circle fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M256 56c110.532 0 200 89.451 200 200 0 110.532-89.451 200-200 200-110.532 0-200-89.451-200-200 0-110.532 89.451-200 200-200m0-48C119.033 8 8 119.033 8 256s111.033 248 248 248 248-111.033 248-248S392.967 8 256 8zm0 168c-44.183 0-80 35.817-80 80s35.817 80 80 80 80-35.817 80-80-35.817-80-80-80z"></path></svg>` },
        { id: 'bs-checkbox-group', label: 'Checkbox Group', media: `<svg aria-hidden="true" width="24" height="50" focusable="false" data-prefix="fas" data-icon="check-square" class="svg-inline--fa fa-check-square fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="currentColor" d="M400 480H48c-26.51 0-48-21.49-48-48V80c0-26.51 21.49-48 48-48h352c26.51 0 48 21.49 48 48v352c0 26.51-21.49 48-48 48zm-204.686-98.059l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.248-16.379-6.249-22.628 0L184 302.745l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.25 16.379 6.25 22.628.001z"></path></svg>` },
        {
            id: 'bs-input-group', label: 'Input Group', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z"></path>
    <polygon class="gjs-block-svg-path" points="4 10 5 10 5 14 4 14"></polygon>
</svg>` },
        {
            id: 'bs-form-action-button', label: 'Action Button', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z" fill-rule="nonzero"></path>
    <rect class="gjs-block-svg-path" x="4" y="11.5" width="16" height="1"></rect>
</svg>` },

        // { id: 'bs-validation', label: 'Validation', media: '⚠️' }
    ];

    formBlocks.forEach(block => {
        BlockManager.add(block.id, {
            label: block.label,
            category: 'Forms',
            media: block.media,
            content: { type: block.id }
        });
    });

    // Add plain input and textarea to the blocks panel
    const coreFormBlocks = [
        {
            id: 'bs-input', label: 'Input', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,9 C22,8.4 21.5,8 20.75,8 L3.25,8 C2.5,8 2,8.4 2,9 L2,15 C2,15.6 2.5,16 3.25,16 L20.75,16 C21.5,16 22,15.6 22,15 L22,9 Z M21,15 L3,15 L3,9 L21,9 L21,15 Z"></path>
    <polygon class="gjs-block-svg-path" points="4 10 5 10 5 14 4 14"></polygon>
</svg>
` },
        {
            id: 'bs-textarea', label: 'Textarea', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,7.5 C22,6.6 21.5,6 20.75,6 L3.25,6 C2.5,6 2,6.6 2,7.5 L2,16.5 C2,17.4 2.5,18 3.25,18 L20.75,18 C21.5,18 22,17.4 22,16.5 L22,7.5 Z M21,17 L3,17 L3,7 L21,7 L21,17 Z"></path>
    <polygon class="gjs-block-svg-path" points="4 8 5 8 5 12 4 12"></polygon>
    <polygon class="gjs-block-svg-path" points="19 7 20 7 20 17 19 17"></polygon>
    <polygon class="gjs-block-svg-path" points="20 8 21 8 21 9 20 9"></polygon>
    <polygon class="gjs-block-svg-path" points="20 15 21 15 21 16 20 16"></polygon>
</svg>` },
        {
            id: 'bs-label', label: 'Label', media: `<svg class="gjs-block-svg" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path class="gjs-block-svg-path" d="M22,11.875 C22,11.35 21.5,11 20.75,11 L3.25,11 C2.5,11 2,11.35 2,11.875 L2,17.125 C2,17.65 2.5,18 3.25,18 L20.75,18 C21.5,18 22,17.65 22,17.125 L22,11.875 Z M21,17 L3,17 L3,12 L21,12 L21,17 Z" fill-rule="nonzero"></path>
    <rect class="gjs-block-svg-path" x="2" y="5" width="14" height="5" rx="0.5"></rect>
    <polygon class="gjs-block-svg-path" fill-rule="nonzero" points="4 13 5 13 5 16 4 16"></polygon>
</svg>` },
    ];

    coreFormBlocks.forEach(block => {
        BlockManager.add(block.id, {
            label: block.label,
            category: 'Forms',
            media: block.media,
            content: { type: block.id }
        });
    });

    // Form Submission Handling
    editor.on('component:selected', component => {
        if (component.get('type') === 'bs-form') {
            editor.runCommand('open-traits');
        }
    });

    // Dynamic Data Handling
    editor.on('trait:update:dataUrl', component => {
        if (component.get('type') === 'bs-select') {
            component.fetchOptions();
        }
    });
});