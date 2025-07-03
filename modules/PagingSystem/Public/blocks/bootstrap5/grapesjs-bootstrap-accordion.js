grapesjs.plugins.add("grapesjs-bootstrap-accordion", function (editor, opts = {}) {
    // Regular Bootstrap Accordion
    editor.Components.addType("bs-accordion", {
        isComponent: el => el.classList?.contains('bs-accordion'),

        model: {
            defaults: {
                tagName: 'div',
                name: 'Accordion',
                draggable: true,
                droppable: false,
                removable: true,
                copyable: true,
                classes: ['bs-accordion', 'accordion'],
                attributes: {
                    id: 'accordion-' + Math.random().toString(36).substring(2, 9)
                },
                traits: [
                    {
                        type: 'number',
                        label: 'Number of Items',
                        name: 'itemCount',
                        min: 1,
                        max: 10,
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Flush Style',
                        name: 'flush',
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Always Open',
                        name: 'alwaysOpen',
                        changeProp: true
                    }
                ],
                itemCount: 3,
                flush: false,
                alwaysOpen: false,
                components: [] // Will be populated by init
            },

            init() {
                this.on('change:itemCount', this.updateItems);
                this.on('change:flush', this.updateFlush);
                this.on('change:alwaysOpen', this.updateBehavior);

                // Initialize with default items
                this.updateItems();
            },

            updateItems() {
                // Use a guard to avoid collisions (if needed)
                if (this._updating) return;
                this._updating = true;

                try {
                    const newCount = this.get('itemCount');
                    const itemsCollection = this.components();
                    const currentCount = itemsCollection.length;
                    const accordionId = this.get('attributes').id;


                    // 1. Remove surplus items (if newCount < currentCount)
                    // Loop backwards to safely remove items
                    for (let i = currentCount - 1; i >= newCount; i--) {
                        const itemToRemove = itemsCollection.at(i);
                        if (itemToRemove) {
                            itemToRemove.remove();
                        }
                    }

                    // 2. Update current items in place or add new ones (if newCount > currentCount)
                    for (let i = 0; i < newCount; i++) {
                        const itemId = `accordion-item-${i}-${Math.random().toString(36).substring(2, 9)}`;
                        let item = itemsCollection.at(i);

                        if (item) {

                            // If the item has no inner components (edge case), add the default inner structure.
                            if (!item.components().length) {
                                item.setName(`Accordion item ${i + 1}`);

                                item.components([

                                    // Header
                                    {
                                        tagName: 'h2',
                                        attributes: {
                                            class: 'accordion-header',
                                            id: `heading-${itemId}`
                                        },
                                        components: {
                                            tagName: 'button',
                                            name: `Accordion button`,
                                            attributes: {
                                                class: 'accordion-button',
                                                type: 'button',
                                                'data-bs-toggle': 'collapse',
                                                'data-bs-target': `#collapse-${itemId}`,
                                                'aria-expanded': i === 0 ? 'true' : 'false',
                                                'aria-controls': `collapse-${itemId}`
                                            },
                                            components: {
                                                type: 'text',
                                                name: `Accordion item title`,
                                                tagName: 'span',
                                                content: `Accordion Item ${i + 1}`,
                                                editable: true
                                            }
                                        }
                                    },
                                    // Collapsible content
                                    {
                                        tagName: 'div',
                                        name: `Accordion collapse`,
                                        classes: ['accordion-collapse', 'collapse', i === 0 ? 'show' : ''],
                                        attributes: {
                                            id: `collapse-${itemId}`,
                                            'aria-labelledby': `heading-${itemId}`,
                                            'data-bs-parent': this.get('alwaysOpen') ? '' : `#${accordionId}`
                                        },
                                        components: {
                                            tagName: 'div',
                                            name: 'Accordion body',
                                            attributes: { class: 'accordion-body' },
                                            components: {
                                                type: 'text',
                                                content: `<p>Content for item ${i + 1}. Add your content here.</p>`,
                                                editable: true
                                            }
                                        }

                                    }
                                ]);
                            }
                        } else {
                            // 3. If there is no item at this index, add a new one.
                            itemsCollection.add({
                                tagName: 'div',
                                name: `Accordion item ${i + 1}`,
                                attributes: { class: 'accordion-item' },
                                components: [
                                    // Header
                                    {
                                        tagName: 'h2',
                                        attributes: {
                                            class: 'accordion-header',
                                            id: `heading-${itemId}`
                                        },
                                        components: {
                                            tagName: 'button',
                                            name: 'Accordion button',
                                            attributes: {
                                                class: 'accordion-button',
                                                type: 'button',
                                                'data-bs-toggle': 'collapse',
                                                'data-bs-target': `#collapse-${itemId}`,
                                                'aria-expanded': i === 0 ? 'true' : 'false',
                                                'aria-controls': `collapse-${itemId}`
                                            },
                                            components: {
                                                type: 'text',
                                                name: 'Accordion item title',
                                                tagName: 'span',
                                                content: `Accordion Item ${i + 1}`,
                                                editable: true
                                            }
                                        }
                                    },
                                    // Collapsible content
                                    {
                                        tagName: 'div',
                                        name: 'Accordion collapse',
                                        classes: ['accordion-collapse', 'collapse', i === 0 ? 'show' : ''],
                                        attributes: {
                                            id: `collapse-${itemId}`,
                                            'aria-labelledby': `heading-${itemId}`,
                                            'data-bs-parent': this.get('alwaysOpen') ? '' : `#${accordionId}`
                                        },
                                        components: {
                                            tagName: 'div',
                                            name: 'Accordion body',
                                            attributes: { class: 'accordion-body' },
                                            components: {
                                                type: 'text',
                                                content: `<p>Content for item ${i + 1}. Add your content here.</p>`,
                                                editable: true
                                            }
                                        }
                                    }
                                ]
                            });
                        }
                    }
                } finally {
                    this._updating = false;
                }
            },

            updateFlush() {
                const flush = this.get('flush');
                flush ? this.addClass('accordion-flush') : this.removeClass('accordion-flush');
            },

            updateBehavior() {
                const alwaysOpen = this.get('alwaysOpen');
                const accordionId = this.get('attributes').id;

                // Update all items
                this.components().forEach((item, i) => {
                    const collapse = item.components().at(1);
                    if (collapse) {
                        alwaysOpen ?
                            collapse.removeAttributes('data-bs-parent') :
                            collapse.addAttributes({ 'data-bs-parent': `#${accordionId}` });
                    }
                });
            }
        }
    });

    // Image Accordion Component
    editor.Components.addType("bs-image-accordion", {
        isComponent: el => el.classList?.contains('bs-image-accordion'),

        model: {
            defaults: {
                tagName: 'div',
                name: 'Image accordion',
                draggable: true,
                droppable: false,
                removable: true,
                copyable: true,
                attributes: {
                    class: 'bs-image-accordion image-accordion',
                    'data-accordion': true,
                },
                traits: [
                    {
                        type: 'number',
                        label: 'Number of Items',
                        name: 'itemCount',
                        min: 2,
                        max: 5,
                        changeProp: true
                    },
                    {
                        type: 'select',
                        label: 'Gradient Style',
                        name: 'gradient',
                        options: [
                            { id: 'dark', name: 'Dark' },
                            { id: 'light', name: 'Light' },
                            { id: 'primary', name: 'Primary' }
                        ],
                        changeProp: true
                    },
                    {
                        type: 'checkbox',
                        label: 'Multiple Open',
                        name: 'multipleOpen',
                        changeProp: true
                    }
                ],
                itemCount: 3,
                gradient: 'dark',
                multipleOpen: false,
                components: [],
                styles: `
    .image-accordion {
      display: flex;
      gap: 10px;
      height: 500px;
      margin: 20px 0;
    }
    
    .image-accordion .accordion-item {
      flex: 1;
      border-radius: 8px;
      background-size: cover;
      background-position: center;
      position: relative;
      cursor: pointer;
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
      overflow: hidden;
    }
    
    .image-accordion .accordion-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image: var(--accordion-gradient);
      z-index: 1;
    }
    
    .image-accordion .accordion-content {
      position: relative;
      z-index: 2;
      color: white;
      padding: 20px;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
    }
    
    .image-accordion .accordion-body {
      max-height: 0;
      opacity: 0;
      overflow: hidden;
      transition: all 0.5s ease;
      font-size: 0.9em;
      line-height: 1.6;
    }
    
    .image-accordion .accordion-item.active {
      flex: 4;
    }
    
    .image-accordion .accordion-item.active .accordion-body {
      max-height: 200px;
      opacity: 1;
      margin-top: 15px;
    }
  `
            },

            init() {
                this.on('change:itemCount', this.updateItems);
                this.on('change:gradient', this.updateGradient);
                this.updateItems();
            },

            updateItems() {
                // Use a guard to avoid collisions (if needed)
                if (this._updating) return;
                this._updating = true;

                try {
                    const newCount = this.get('itemCount');
                    const itemsCollection = this.components();
                    const currentCount = itemsCollection.length;
                    // const defaultBg = 'https://static.vecteezy.com/system/resources/previews/022/907/004/large_2x/world-earth-day-concept-illustration-of-the-green-planet-earth-on-a-white-background-earth-day-poster-banner-card-april-22-saving-the-planet-environment-planet-earth-generate-ai-free-photo.jpg';
                    const defaultBg = 'https://placehold.co/400';

                    // 1. Remove surplus items (if newCount < currentCount)
                    // Loop backwards to safely remove items
                    for (let i = currentCount - 1; i >= newCount; i--) {
                        const itemToRemove = itemsCollection.at(i);
                        if (itemToRemove) {
                            itemToRemove.remove();
                        }
                    }

                    // 2. Update current items in place or add new ones (if newCount > currentCount)
                    for (let i = 0; i < newCount; i++) {
                        let item = itemsCollection.at(i);
                        let bgImage = `url(${defaultBg})`;

                        if (item) {
                            // Preserve a custom background image if it exists from a previous change.
                            // const styleAttr = item.getAttributes().style || "";
                            // const match = styleAttr.match(/background-image:\s*url\((.*?)\)/);
                            // if (match) {
                            //     bgImage = `url(${match[1]})`;
                            // }

                            // Update only the style attribute without touching inner components
                            // (This sets the background image but leaves inner HTML intact)
                            // item.setAttributes({
                            //     style: `background-image: ${bgImage}`
                            // });
                            // item.setStyle({ 'background-image': bgImage });
                            // item.setStyle({ ...styleAttr });

                            // If the item has no inner components (edge case), add the default inner structure.
                            if (!item.components().length) {
                                item.setName('Accordion item');
                                item.components([
                                    {
                                        tagName: "div",
                                        name: 'Accordion content',
                                        attributes: { class: "accordion-content" },
                                        components: [
                                            {
                                                type: "text",
                                                tagName: "h3",
                                                content: `Item ${i + 1}`
                                            },
                                            {
                                                tagName: "div",
                                                name: 'Accordion body',
                                                attributes: { class: "accordion-body" },
                                                components: {
                                                    type: "text",
                                                    content: `Content for item ${i + 1}. Click to expand.`,
                                                    editable: true
                                                }
                                            }
                                        ]
                                    }
                                ]);
                            }
                        } else {
                            // 3. If there is no item at this index, add a new one.
                            itemsCollection.add({
                                tagName: "div",
                                name: 'Accordion item',
                                attributes: {
                                    class: "accordion-item",
                                    // style: `background-image: ${bgImage}`
                                },
                                style: { 'background-image': bgImage },
                                components: [
                                    {
                                        tagName: "div",
                                        name: 'Accordion content',
                                        attributes: { class: "accordion-content" },
                                        components: [
                                            {
                                                type: "text",
                                                tagName: "h3",
                                                components: `Item ${i + 1}`
                                            },
                                            {
                                                tagName: "div",
                                                name: 'Accordion body',
                                                attributes: { class: "accordion-body" },
                                                components: {
                                                    type: "text",
                                                    content: `Content for item ${i + 1}. Click to expand.`,
                                                    editable: true
                                                }
                                            }
                                        ]
                                    }
                                ]
                            });
                        }
                    }
                    // Finally, update the gradient after making changes.
                    this.updateGradient();
                } finally {
                    this._updating = false;
                }
            },

            updateGradient() {
                const gradientType = this.get('gradient');
                const gradientMap = {
                    dark: 'linear-gradient(to top, rgba(0,0,0,0.7), rgba(0,0,0,0.0), rgba(0,0,0,0.0))',
                    light: 'linear-gradient(to top, rgba(255,255,255,0.7), rgba(255,255,255,0.0), rgba(255,255,255,0.0))',
                    primary: 'linear-gradient(to top, var(--bs-primary), rgba(var(--bs-primary-rgb), 0.0), rgba(var(--bs-primary-rgb), 0.0))'
                };

                this.components().forEach(item => {
                    // Retrieve the current style object – ensure it’s in object format
                    const currentStyles = item.getStyle() || {};
                    // Merge the custom property into the existing style object
                    item.setStyle({
                        ...currentStyles,
                        '--accordion-gradient': gradientMap[gradientType]
                    });
                });

            }
        }
    });

    // Add JavaScript Interaction
    // Corrected component view extension
    editor.on('load', () => {
        const compType = editor.DomComponents.getType('bs-image-accordion');

        // Properly extend the view
        editor.DomComponents.addType('bs-image-accordion', {
            view: compType.view.extend({
                events: {
                    'click': 'handleClick'
                },

                init() {
                    // Listen to trait changes
                    this.listenTo(this.model, 'change:multipleOpen', this.render);
                },

                handleClick(e) {
                    const target = e.target.closest('.accordion-item');
                    if (!target) return;

                    const multipleOpen = this.model.get('multipleOpen');
                    const items = this.el.querySelectorAll('.accordion-item');

                    items.forEach(item => {
                        if (item === target) {
                            item.classList.toggle('active', multipleOpen ? undefined : true);
                        } else if (!multipleOpen) {
                            item.classList.remove('active');
                        }
                    });

                    // Update component state
                    this.el.querySelectorAll('.accordion-item').forEach((item, index) => {
                        let accordionItemComponent = this.model.components().at(index);
                        // Get existing attributes first.
                        const currentAttrs = accordionItemComponent.getAttributes();
                        // Merge the new class attribute while preserving existing ones (including style)
                        accordionItemComponent.setAttributes({
                            ...currentAttrs,
                            class: item.classList.contains('active') ? 'accordion-item active' : 'accordion-item',
                        });
                    });
                }
            })
        });
    });

    // Add to Blocks Panel
    editor.Blocks.add("bs-image-accordion", {
        label: "Image Accordion",
        category: "Bootstrap Component",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M384 160c-35.3 0-64 28.7-64 64V448c0 35.3 28.7 64 64 64H544c35.3 0 64-28.7 64-64V224c0-35.3-28.7-64-64-64H384zM256 160H64c-35.3 0-64 28.7-64 64V448c0 35.3 28.7 64 64 64H256c35.3 0 64-28.7 64-64V224c0-35.3-28.7-64-64-64zM0 32C0 14.3 14.3 0 32 0H544c17.7 0 32 14.3 32 32V96c0 17.7-14.3 32-32 32H32C14.3 128 0 113.7 0 96V32z"/></svg>`,
        content: { type: "bs-image-accordion" }
    });

    // Add to blocks panel
    editor.Blocks.add("bs-accordion", {
        label: "Accordion",
        category: "Bootstrap Component",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M0 96C0 60.7 28.7 32 64 32H448c35.3 0 64 28.7 64 64V416c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V96zm64 64v64H448V160H64zm0 128v64H448V288H64z"/></svg>`,
        content: { type: "bs-accordion" }
    });
});