grapesjs.plugins.add("grapesjs-bootstrap-typography", function (editor, opts = {}) {
    const defaults = {
        // Heading options
        headingLevels: [1, 2, 3, 4, 5, 6],
        displayLevels: [1, 2, 3, 4, 5, 6],
        // Text alignment
        textAlignments: [
            { id: '', name: 'Default' },
            { id: 'text-start', name: 'Left' },
            { id: 'text-center', name: 'Center' },
            { id: 'text-end', name: 'Right' }
        ],
        // Text transforms
        textTransforms: [
            { id: '', name: 'Normal' },
            { id: 'text-lowercase', name: 'Lowercase' },
            { id: 'text-uppercase', name: 'Uppercase' },
            { id: 'text-capitalize', name: 'Capitalize' }
        ],
        // Font weights
        fontWeights: [
            { id: '', name: 'Normal' },
            { id: 'fw-bold', name: 'Bold' },
            { id: 'fw-bolder', name: 'Bolder' },
            { id: 'fw-light', name: 'Light' },
            { id: 'fw-lighter', name: 'Lighter' }
        ],
        // Default content
        defaultHeadingText: 'Heading',
        defaultParagraphText: 'This is a sample paragraph text.',
        defaultLeadText: 'This is a lead paragraph—it stands out from regular paragraphs.'
    };

    const options = { ...defaults, ...opts };

    // 1. HEADING COMPONENT ===================================
    editor.Components.addType("bs-heading", {
        isComponent: el => el.tagName?.match(/^H[1-6]$/) && el.classList?.contains('bs-heading'),

        model: {
            defaults: {
                tagName: 'h1',
                name: 'Heading',
                draggable: true,
                removable: true,
                editable: true,
                droppable: true,
                highlightable: true,
                attributes: {
                    class: 'bs-heading'
                },
                traits: [
                    {
                        type: 'select',
                        label: 'Heading Level',
                        name: 'headingLevel',
                        options: options.headingLevels.map(lvl => ({
                            id: lvl,
                            name: `Heading ${lvl}`
                        })),
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Display Heading',
                        name: 'displayLevel',
                        options: [
                            { id: '', name: 'Normal' },
                            ...options.displayLevels.map(lvl => ({
                                id: lvl,
                                name: `Display ${lvl}`
                            }))
                        ],
                        changeProp: true
                    },
                    // {
                    //     type: 'select',
                    //     label: 'Alignment',
                    //     name: 'textAlign',
                    //     options: options.textAlignments,
                    //     changeProp: true
                    // },
                    {
                        type: 'select',
                        label: 'Text Transform',
                        name: 'textTransform',
                        options: options.textTransforms,
                        changeProp: true
                    },
                    // {
                    //     type: 'select',
                    //     label: 'Font Weight',
                    //     name: 'fontWeight',
                    //     options: options.fontWeights,
                    //     changeProp: true
                    // }
                ],
                components:
                {
                    type: 'text',
                    tagName: 'span',
                    content: 'Heading',
                    editable: true
                }
                ,
                headingLevel: 1,
                displayLevel: '',
                textAlign: '',
                textTransform: '',
                fontWeight: ''
            },

            init() {
                this.on('change:headingLevel', this.updateHeadingLevel);
                this.on('change:displayLevel', this.updateDisplayClass);
                this.on('change:textAlign', this.updateTextAlign);
                this.on('change:textTransform', this.updateTextTransform);
                this.on('change:fontWeight', this.updateFontWeight);
            },

            updateHeadingLevel() {
                const level = this.get('headingLevel');
                this.set('tagName', `h${level}`);

                // Remove all heading classes
                options.headingLevels.forEach(lvl => {
                    this.removeClass(`h${lvl}`);
                });

                // Add new heading class if specified
                if (level) {
                    this.addClass(`h${level}`);
                }
            },

            updateDisplayClass() {
                const level = this.get('displayLevel');

                // Remove all display classes
                options.displayLevels.forEach(lvl => {
                    this.removeClass(`display-${lvl}`);
                });

                // Add new display class if specified
                if (level) {
                    this.addClass(`display-${level}`);
                }
            },

            updateTextAlign() {
                const align = this.get('textAlign');

                // Remove all alignment classes
                options.textAlignments.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                // Add new alignment if specified
                if (align) this.addClass(align);
            },

            updateTextTransform() {
                const transform = this.get('textTransform');

                // Remove all transform classes
                options.textTransforms.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                // Add new transform if specified
                if (transform) this.addClass(transform);
            },

            updateFontWeight() {
                const weight = this.get('fontWeight');

                // Remove all weight classes
                options.fontWeights.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                // Add new weight if specified
                if (weight) this.addClass(weight);
            }
        }
    });

    // 2. PARAGRAPH COMPONENT =================================
    editor.Components.addType("bs-paragraph", {
        isComponent: el => el.tagName === 'P' && el.classList.contains('bs-paragraph'),

        model: {
            defaults: {
                tagName: 'p',
                name: 'Paragraph',
                draggable: true,
                droppable: true,
                editable: true,
                attributes: {
                    class: 'bs-paragraph'
                },
                traits: [
                    {
                        type: 'checkbox',
                        label: 'Lead Paragraph',
                        name: 'lead',
                        changeProp: true
                    },
                    // {
                    //     type: 'select',
                    //     label: 'Alignment',
                    //     name: 'textAlign',
                    //     options: options.textAlignments,
                    //     changeProp: true
                    // },
                    {
                        type: 'select',
                        label: 'Text Transform',
                        name: 'textTransform',
                        options: options.textTransforms,
                        changeProp: true
                    },
                    // {
                    //     type: 'select',
                    //     label: 'Font Weight',
                    //     name: 'fontWeight',
                    //     options: options.fontWeights,
                    //     changeProp: true
                    // }
                ],
                components: {
                    type: 'text',
                    tagName: 'span',
                    editable: true,
                    content: options.defaultParagraphText,
                },
                lead: false,
                textAlign: '',
                textTransform: '',
                fontWeight: ''
            },

            init() {
                this.on('change:lead', this.updateLeadClass);
                this.on('change:textAlign', this.updateTextAlign);
                this.on('change:textTransform', this.updateTextTransform);
                this.on('change:fontWeight', this.updateFontWeight);
            },

            updateLeadClass() {
                const lead = this.get('lead');
                lead ? this.addClass('lead') : this.removeClass('lead');
            },

            updateTextAlign() {
                const align = this.get('textAlign');

                options.textAlignments.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (align) this.addClass(align);
            },

            updateTextTransform() {
                const transform = this.get('textTransform');

                options.textTransforms.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (transform) this.addClass(transform);
            },

            updateFontWeight() {
                const weight = this.get('fontWeight');

                options.fontWeights.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (weight) this.addClass(weight);
            }
        }
    });

    // 3. TEXT COMPONENT (Generic) ============================
    editor.Components.addType("bs-text", {
        isComponent: el => el.classList?.contains('bs-text'),

        model: {
            defaults: {
                // tagName: 'span',
                name: 'Text',
                draggable: true,
                droppable: true,
                editable: true,
                attributes: {
                    class: 'bs-text'
                },
                traits: [
                    {
                        type: 'select',
                        label: 'Text Color',
                        name: 'textColor',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'text-primary', name: 'Primary' },
                            { id: 'text-secondary', name: 'Secondary' },
                            { id: 'text-success', name: 'Success' },
                            { id: 'text-danger', name: 'Danger' },
                            { id: 'text-warning', name: 'Warning' },
                            { id: 'text-info', name: 'Info' },
                            { id: 'text-light', name: 'Light' },
                            { id: 'text-dark', name: 'Dark' },
                            { id: 'text-muted', name: 'Muted' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Alignment',
                        name: 'textAlign',
                        options: options.textAlignments,
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Text Transform',
                        name: 'textTransform',
                        options: options.textTransforms,
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Font Weight',
                        name: 'fontWeight',
                        options: options.fontWeights,
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Inline Code',
                        name: 'code',
                        changeProp: true
                    }
                ],
                components: {
                    type: 'text',
                    tagName: 'span',
                    editable: true,
                    content: 'Custom text',
                },
                textColor: '',
                textAlign: '',
                textTransform: '',
                fontWeight: '',
                code: false
            },

            init() {
                this.on('change:textColor', this.updateTextColor);
                this.on('change:textAlign', this.updateTextAlign);
                this.on('change:textTransform', this.updateTextTransform);
                this.on('change:fontWeight', this.updateFontWeight);
                this.on('change:code', this.updateCode);
            },

            updateTextColor() {
                const color = this.get('textColor');

                // Remove all color classes
                [
                    'text-primary', 'text-secondary', 'text-success',
                    'text-danger', 'text-warning', 'text-info',
                    'text-light', 'text-dark', 'text-muted'
                ].forEach(cls => this.removeClass(cls));

                // Add new color if specified
                if (color) this.addClass(color);
            },

            updateTextAlign() {
                const align = this.get('textAlign');

                options.textAlignments.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (align) this.addClass(align);
            },

            updateTextTransform() {
                const transform = this.get('textTransform');

                options.textTransforms.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (transform) this.addClass(transform);
            },

            updateFontWeight() {
                const weight = this.get('fontWeight');

                options.fontWeights.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (weight) this.addClass(weight);
            },

            updateCode() {
                const code = this.get('code');
                if (code) {
                    this.addClass('font-monospace');
                    this.set('tagName', 'code');
                } else {
                    this.removeClass('font-monospace');
                    this.set('tagName', 'span');
                }
            }
        }
    });

    // 4. LIST COMPONENTS =====================================
    const listTypes = ['ul', 'ol'];

    listTypes.forEach(listType => {
        editor.Components.addType(`bs-list-${listType}`, {
            isComponent: el => el.tagName === listType.toUpperCase() && el.classList.contains('bs-list'),

            model: {
                defaults: {
                    tagName: listType,
                    name: `List (${listType.toUpperCase()})`,
                    draggable: true,
                    droppable: 'li',
                    editable: true,
                    attributes: {
                        class: 'bs-list'
                    },
                    traits: [
                        {
                            type: 'select',
                            label: 'List Style',
                            name: 'listStyle',
                            options: [
                                { id: '', name: 'Default' },
                                { id: 'list-unstyled', name: 'Unstyled' },
                                { id: 'list-inline', name: 'Inline' }
                            ],
                            changeProp: true
                        }
                    ],
                    components: Array(3).fill().map((_, i) => ({
                        tagName: 'li',
                        components: { type: 'text', content: `List item ${i + 1}` }
                    })),
                    listStyle: ''
                },

                init() {
                    this.on('change:listStyle', this.updateListStyle);
                },

                updateListStyle() {
                    const style = this.get('listStyle');

                    // Remove all list style classes
                    ['list-unstyled', 'list-inline'].forEach(cls => this.removeClass(cls));

                    // Add new style if specified
                    if (style) this.addClass(style);
                }
            }
        });
    });

    // 5. BLOCKQUOTE COMPONENT ===============================
    editor.Components.addType("bs-blockquote", {
        isComponent: el => el.tagName === 'BLOCKQUOTE' && el.classList.contains('bs-blockquote'),

        model: {
            defaults: {
                tagName: 'blockquote',
                name: 'Block quote',
                draggable: true,
                droppable: true,
                editable: true,
                attributes: {
                    class: 'bs-blockquote'
                },
                traits: [
                    {
                        type: 'select',
                        label: 'Alignment',
                        name: 'textAlign',
                        options: options.textAlignments,
                        changeProp: true
                    }
                ],
                components: [
                    {
                        tagName: 'p',
                        components: {
                            type: 'text',
                            content: 'A well-known quote, contained in a blockquote element.'
                        }
                    },
                    {
                        tagName: 'footer',
                        attributes: { class: 'blockquote-footer' },
                        components: {
                            type: 'text',
                            content: 'Someone famous in '
                        },
                        components: [
                            {
                                tagName: 'cite',
                                components: {
                                    type: 'text',
                                    content: 'Source Title'
                                }
                            }
                        ]
                    }
                ],
                textAlign: ''
            },

            init() {
                this.on('change:textAlign', this.updateTextAlign);
            },

            updateTextAlign() {
                const align = this.get('textAlign');

                options.textAlignments.forEach(opt => {
                    if (opt.id) this.removeClass(opt.id);
                });

                if (align) this.addClass(align);
            }
        }
    });

    // ADD TO BLOCKS PANEL ===================================
    const blockIcons = {
        heading: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M0 64C0 46.3 14.3 32 32 32H80h48c17.7 0 32 14.3 32 32s-14.3 32-32 32H112V208H336V96H320c-17.7 0-32-14.3-32-32s14.3-32 32-32h48 48c17.7 0 32 14.3 32 32s-14.3 32-32 32H400V240 416h16c17.7 0 32 14.3 32 32s-14.3 32-32 32H368 320c-17.7 0-32-14.3-32-32s14.3-32 32-32h16V272H112V416h16c17.7 0 32 14.3 32 32s-14.3 32-32 32H80 32c-17.7 0-32-14.3-32-32s14.3-32 32-32H48V240 96H32C14.3 96 0 81.7 0 64z"/></svg>`,
        paragraph: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M448 64c0-17.7-14.3-32-32-32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32zm0 256c0-17.7-14.3-32-32-32H32c-17.7 0-32 14.3-32 32s14.3 32 32 32H416c17.7 0 32-14.3 32-32zM0 192c0 17.7 14.3 32 32 32H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H32c-17.7 0-32 14.3-32 32zM448 448c0-17.7-14.3-32-32-32H32c-17.7 0-32 14.3-32 32s14.3 32 32 32H416c17.7 0 32-14.3 32-32z"/></svg>`,
        text: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M254 52.8C249.3 40.3 237.3 32 224 32s-25.3 8.3-30 20.8L57.8 416H32c-17.7 0-32 14.3-32 32s14.3 32 32 32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32h-1.8l18-48H303.8l18 48H320c-17.7 0-32 14.3-32 32s14.3 32 32 32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H390.2L254 52.8zM279.8 304H168.2L224 155.1 279.8 304z"/></svg>`,
        list: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M40 48C26.7 48 16 58.7 16 72v48c0 13.3 10.7 24 24 24H88c13.3 0 24-10.7 24-24V72c0-13.3-10.7-24-24-24H40zM192 64c-17.7 0-32 14.3-32 32s14.3 32 32 32H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H192zm0 160c-17.7 0-32 14.3-32 32s14.3 32 32 32H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H192zm0 160c-17.7 0-32 14.3-32 32s14.3 32 32 32H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H192zM16 232v48c0 13.3 10.7 24 24 24H88c13.3 0 24-10.7 24-24V232c0-13.3-10.7-24-24-24H40c-13.3 0-24 10.7-24 24zM40 368c-13.3 0-24 10.7-24 24v48c0 13.3 10.7 24 24 24H88c13.3 0 24-10.7 24-24V392c0-13.3-10.7-24-24-24H40z"/></svg>`,
        blockquote: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M0 216C0 149.7 53.7 96 120 96h8c17.7 0 32 14.3 32 32s-14.3 32-32 32h-8c-30.9 0-56 25.1-56 56v8h64c35.3 0 64 28.7 64 64v64c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V216zM328 96h8c66.3 0 120 53.7 120 120v168c0 35.3-28.7 64-64 64H288c-35.3 0-64-28.7-64-64v-64c0-35.3 28.7-64 64-64h64v-8c0-30.9-25.1-56-56-56h-8c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg>`
    };

    editor.Blocks.add(`bs-heading`, {
        label: `Heading`,
        category: 'Typography',
        media: blockIcons.heading,
        content: {
            type: 'bs-heading',
            headingLevel: 1,
        }
    });

    // Add other typography blocks
    editor.Blocks.add("bs-paragraph", {
        label: "Paragraph",
        category: "Typography",
        media: blockIcons.paragraph,
        content: {
            type: "bs-paragraph",
            content: options.defaultParagraphText
        }
    });

    editor.Blocks.add("bs-lead-paragraph", {
        label: "Lead Paragraph",
        category: "Typography",
        media: blockIcons.paragraph,
        content: {
            type: "bs-paragraph",
            content: options.defaultLeadText,
            lead: true,
            attributes: { class: 'bs-paragraph lead' }
        }
    });

    editor.Blocks.add("bs-text", {
        label: "Text",
        category: "Typography",
        media: blockIcons.text,
        content: {
            type: "bs-text",
            // content: "Custom text"
        }
    });

    editor.Blocks.add("bs-list-ul", {
        label: "Bulleted List",
        category: "Typography",
        media: blockIcons.list,
        content: {
            type: "bs-list-ul",
            components: Array(3).fill().map((_, i) => ({
                tagName: 'li',
                components: { type: 'text', content: `List item ${i + 1}` }
            }))
        }
    });

    editor.Blocks.add("bs-list-ol", {
        label: "Numbered List",
        category: "Typography",
        media: blockIcons.list,
        content: {
            type: "bs-list-ol",
            components: Array(3).fill().map((_, i) => ({
                tagName: 'li',
                components: { type: 'text', content: `List item ${i + 1}` }
            }))
        }
    });

    editor.Blocks.add("bs-blockquote", {
        label: "Blockquote",
        category: "Typography",
        media: blockIcons.blockquote,
        content: { type: "bs-blockquote" }
    });
});