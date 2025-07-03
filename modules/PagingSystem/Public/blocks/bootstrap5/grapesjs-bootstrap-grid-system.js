grapesjs.plugins.add("grapesjs-bootstrap-grid-system", function (editor, opts = {}) {
    const defaults = {
        // Default column classes
        defaultColClasses: 'col',
        // Breakpoints for responsive columns
        breakpoints: [
            { id: '', name: 'Extra Small (default)' },
            { id: 'sm', name: 'Small' },
            { id: 'md', name: 'Medium' },
            { id: 'lg', name: 'Large' },
            { id: 'xl', name: 'Extra Large' },
            { id: 'xxl', name: 'XX Large' }
        ],
        // Column width options
        colWidths: Array.from({ length: 12 }, (_, i) => ({
            id: i + 1,
            name: `${i + 1}/12`
        }))
    };

    const options = { ...defaults, ...opts };

    // 1. CONTAINER COMPONENT ==================================
    editor.Components.addType("bs-container", {
        isComponent: el => el.classList?.contains('bs-container'),

        model: {
            defaults: {
                droppable: 'div.row', // Only allow rows to be dropped
                draggable: true,
                removable: true,
                copyable: true,
                name: 'Container',
                attributes: { class: 'bs-container container' },
                traits: [
                    {
                        type: 'select',
                        label: 'Container Type',
                        name: 'containerType',
                        options: [
                            { id: 'container', name: 'Fixed width' },
                            { id: 'container-fluid', name: 'Full width' }
                        ],
                        changeProp: true
                    }
                ],
                containerType: 'container',
                components: [
                    {
                        type: 'bs-row',
                        components: [
                            {
                                type: 'bs-col',
                                attributes: { class: options.defaultColClasses }
                            }
                        ]
                    }
                ]
            },

            init() {
                this.on('change:containerType', this.updateContainerClass);
            },

            updateContainerClass() {
                const type = this.get('containerType');
                this.removeClass('container container-fluid');
                this.addClass(type);
            }
        },

        view: {
            onRender() {
                // Ensure at least one row exists
                if (!this.model.components().length) {
                    this.model.append({
                        type: 'bs-row'
                    });
                }
            }
        }
    });

    // 2. ROW COMPONENT =======================================
    editor.Components.addType("bs-row", {
        isComponent: el => el.classList?.contains('bs-row'),

        model: {
            defaults: {
                name: 'Row',
                droppable: 'div[class*="col-"], div.col', // Allow both col and col-* variants
                draggable: true,
                removable: true,
                copyable: true,
                attributes: { class: 'bs-row row' },
                traits: [
                    {
                        type: 'select',
                        label: 'Gutters',
                        name: 'gutters',
                        options: [
                            { id: 'g-0', name: 'No gutters' },
                            { id: 'g-1', name: 'Gutter 1' },
                            { id: 'g-2', name: 'Gutter 2' },
                            { id: 'g-3', name: 'Gutter 3' },
                            { id: 'g-4', name: 'Gutter 4' },
                            { id: 'g-5', name: 'Gutter 5' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Vertical Align',
                        name: 'alignItems',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'align-items-start', name: 'Top' },
                            { id: 'align-items-center', name: 'Middle' },
                            { id: 'align-items-end', name: 'Bottom' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Horizontal Align',
                        name: 'justifyContent',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'justify-content-start', name: 'Start' },
                            { id: 'justify-content-center', name: 'Center' },
                            { id: 'justify-content-end', name: 'End' },
                            { id: 'justify-content-around', name: 'Around' },
                            { id: 'justify-content-between', name: 'Between' },
                            { id: 'justify-content-evenly', name: 'Evenly' }
                        ],
                        changeProp: true
                    }
                ],
                components: [
                    {
                        type: 'bs-col',
                        attributes: { class: options.defaultColClasses }
                    }
                ]
            },

            init() {
                this.on('change:gutters', this.updateGutters);
                this.on('change:alignItems', this.updateAlignment);
                this.on('change:justifyContent', this.updateJustify);
            },

            updateGutters() {
                const gutter = this.get('gutters');
                this.removeClass('g-0 g-1 g-2 g-3 g-4 g-5');
                if (gutter) this.addClass(gutter);
            },

            updateAlignment() {
                const align = this.get('alignItems');
                this.removeClass('align-items-start align-items-center align-items-end');
                if (align) this.addClass(align);
            },

            updateJustify() {
                const justify = this.get('justifyContent');
                this.removeClass('justify-content-start justify-content-center justify-content-end justify-content-around justify-content-between justify-content-evenly');
                if (justify) this.addClass(justify);
            }
        },

        // view: {
        //     onRender() {
        //         // Ensure at least one column exists
        //         if (!this.model.components().length) {
        //             this.model.append({
        //                 type: 'bs-col',
        //                 attributes: { class: options.defaultColClasses }
        //             });
        //         }
        //     },

        //     // Handle column drops
        //     onDrop(event, target) {
        //         const draggedModel = event.dataTransfer.get('component');

        //         // If dropping a non-column, wrap it in a column
        //         if (!draggedModel || !draggedModel.get('type').startsWith('bs-col')) {
        //             const newCol = editor.Components.addComponent({
        //                 type: 'bs-col',
        //                 attributes: { class: 'col' },
        //                 components: [draggedModel.toJSON()]
        //             });

        //             // Remove original component
        //             if (draggedModel) draggedModel.remove();

        //             // Add new column with content
        //             this.model.append(newCol);
        //             return false; // Prevent default drop
        //         }

        //         return true; // Allow normal drop for columns
        //     }
        // }
    });

    // 3. ENHANCED COLUMN COMPONENT ============================
    editor.Components.addType("bs-col", {
        isComponent: el => el.classList?.contains('bs-col'),

        model: {
            defaults: {
                name: 'Column',
                droppable: true,
                draggable: true,
                removable: true,
                copyable: true,
                editable: true,
                attributes: { class: 'bs-col col' }, // Start with basic col class
                traits: [
                    // Add width control for each breakpoint
                    ...options.breakpoints.map(bp => ({
                        type: 'select',
                        label: `Width (${bp.name})`,
                        name: `colWidth${bp.id ? `-${bp.id}` : ''}`,
                        options: options.colWidths,
                        changeProp: true
                    })),
                    // Alignment and order traits
                    {
                        type: 'select',
                        label: 'Vertical Align',
                        name: 'colAlign',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'start', name: 'Top' },
                            { id: 'center', name: 'Middle' },
                            { id: 'end', name: 'Bottom' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Order',
                        name: 'colOrder',
                        options: [
                            { id: '', name: 'Default' },
                            { id: 'first', name: 'First' },
                            { id: 'last', name: 'Last' },
                            ...Array.from({ length: 6 }, (_, i) => ({
                                id: i + 1,
                                name: `Order ${i + 1}`
                            }))
                        ],
                        changeProp: true
                    }
                ],
                components: {
                    type: 'text',
                    content: 'Column content',
                    attributes: { class: 'my-3' },
                    editable: true
                }
            },

            init() {
                // Listen to all width trait changes
                options.breakpoints.forEach(bp => {
                    this.on(`change:colWidth${bp.id ? `-${bp.id}` : ''}`, this.updateColumnClasses);
                });

                this.on('change:colAlign', this.updateAlignClass);
                this.on('change:colOrder', this.updateOrderClass);
            },

            // Update all column width classes
            updateColumnClasses() {
                // Start with clean slate
                this.removeClass('col');
                for (let i = 1; i <= 12; i++) {
                    this.removeClass(`col-${i}`);
                    options.breakpoints.forEach(bp => {
                        if (bp.id) this.removeClass(`col-${bp.id}-${i}`);
                    });
                }

                // Add classes for each breakpoint
                options.breakpoints.forEach(bp => {
                    const width = this.get(`colWidth${bp.id ? `-${bp.id}` : ''}`);
                    if (width) {
                        this.addClass(bp.id ? `col-${bp.id}-${width}` : `col-${width}`);
                    } else if (!bp.id) {
                        // Always add base 'col' class if no width specified for XS
                        this.addClass('col');
                    }
                });
            },

            // Update alignment class
            updateAlignClass() {
                const align = this.get('colAlign');

                // Remove all alignment classes
                this.removeClass(
                    'align-self-start',
                    'align-self-center',
                    'align-self-end'
                );

                // Add new class if specified
                if (align) {
                    this.addClass(`align-self-${align}`);
                }
            },

            // Update order class
            updateOrderClass() {
                const order = this.get('colOrder');

                // Remove all order classes
                this.removeClass('order-first', 'order-last');
                for (let i = 1; i <= 6; i++) {
                    this.removeClass(`order-${i}`);
                }

                // Add new class if specified
                if (order) {
                    if (order === 'first') {
                        this.addClass('order-first');
                    } else if (order === 'last') {
                        this.addClass('order-last');
                    } else {
                        this.addClass(`order-${order}`);
                    }
                }
            }
        },

        view: {
            // Ensure column remains editable
            onRender() {
                // this.el.contentEditable = true;
            }
        }
    });

    // BLOCKS ================================================
    editor.Blocks.add("bs-container", {
        label: "Container",
        category: "Bootstrap Grid",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M448 32H64C28.65 32 0 60.65 0 96v320c0 35.35 28.65 64 64 64h384c35.35 0 64-28.65 64-64V96C512 60.65 483.3 32 448 32zM64 432c-8.836 0-16-7.164-16-16V96c0-8.836 7.164-16 16-16h384c8.836 0 16 7.164 16 16v320c0 8.836-7.164 16-16 16H64z"/></svg>`,
        content: { type: "bs-container" },
        activate: true
    });

    editor.Blocks.add("bs-row", {
        label: "Row",
        category: "Bootstrap Grid",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M448 32H64C28.65 32 0 60.65 0 96v320c0 35.35 28.65 64 64 64h384c35.35 0 64-28.65 64-64V96C512 60.65 483.3 32 448 32zM64 432c-8.836 0-16-7.164-16-16V96c0-8.836 7.164-16 16-16h384c8.836 0 16 7.164 16 16v320c0 8.836-7.164 16-16 16H64zM96 128h320c17.67 0 32 14.33 32 32v192c0 17.67-14.33 32-32 32H96c-17.67 0-32-14.33-32-32V160C64 142.3 78.33 128 96 128z"/></svg>`,
        content: { type: "bs-row" },
        activate: true
    });

    editor.Blocks.add("bs-col", {
        label: "Column",
        category: "Bootstrap Grid",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M0 96C0 60.65 28.65 32 64 32h384c35.35 0 64 28.65 64 64v320c0 35.35-28.65 64-64 64H64c-35.35 0-64-28.65-64-64V96zM128 432c8.836 0 16-7.164 16-16V96c0-8.836-7.164-16-16-16H64c-8.836 0-16 7.164-16 16v320c0 8.836 7.164 16 16 16H128z"/></svg>`,
        content: { type: "bs-col" },
        activate: true
    });

    // COMMANDS ==============================================
    editor.Commands.add('insert-row', {
        run(editor, sender, opts = {}) {
            const selected = editor.getSelected();

            // Only allow inserting rows into containers
            if (selected && selected.is('bs-container')) {
                const row = editor.Components.addComponent({
                    type: 'bs-row',
                    components: [
                        {
                            type: 'bs-col',
                            attributes: { class: options.defaultColClasses }
                        }
                    ]
                });

                selected.append(row);
            }
        }
    });

    // KEYBOARD SHORTCUTS ===================================
    editor.on('load', () => {
        editor.Keymaps.add('insert-row', 'ctrl+alt+r', 'insert-row');
    });
});