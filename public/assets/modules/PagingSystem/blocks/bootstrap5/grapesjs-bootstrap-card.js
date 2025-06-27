grapesjs.plugins.add("grapesjs-bootstrap-card", function (editor, opts = {}) {
    const defaults = {
        cardTypes: [
            { id: 'default', name: 'Default' },
            { id: 'primary', name: 'Primary' },
            { id: 'secondary', name: 'Secondary' },
            { id: 'success', name: 'Success' },
            { id: 'danger', name: 'Danger' },
            { id: 'warning', name: 'Warning' },
            { id: 'info', name: 'Info' },
            { id: 'light', name: 'Light' },
            { id: 'dark', name: 'Dark' }
        ],
        imagePositions: [
            { id: 'top', name: 'Top' },
            { id: 'bottom', name: 'Bottom' }
        ],
        defaultCardType: 'default',
        defaultImagePosition: 'top',
        defaultImageUrl: 'https://via.placeholder.com/300x200'
    };

    const options = { ...defaults, ...opts };

    // Define the component
    editor.Components.addType("grapesjs-bootstrap-card", {
        isComponent: (el) => el.classList?.contains('card-container'),

        model: {
            defaults: {
                name: 'Card',
                droppable: true,
                editable: true,
                draggable: true,
                removable: true,
                copyable: true,
                traits: [
                    {
                        type: "select",
                        label: "Card Style",
                        name: "cardType",
                        options: options.cardTypes,
                        changeProp: true
                    },
                    {
                        type: "select",
                        label: "Image Position",
                        name: "imagePosition",
                        options: options.imagePositions,
                        changeProp: true
                    },
                    {
                        type: "text",
                        label: "Image Source",
                        name: "imageUrl",
                        changeProp: true
                    },
                    {
                        type: "checkbox",
                        label: "Show Header",
                        name: "showHeader",
                        changeProp: true
                    },
                    {
                        type: "checkbox",
                        label: "Show Footer",
                        name: "showFooter",
                        changeProp: true
                    }
                ],
                cardType: options.defaultCardType,
                imagePosition: options.defaultImagePosition,
                imageUrl: options.defaultImageUrl,
                showHeader: true,
                showFooter: true,
                attributes: { class: 'card' },
                components: [
                    {
                        type: 'text',
                        tagName: 'div',
                        name: 'Card header',
                        attributes: { class: 'card-header' },
                        content: 'Card Header',
                        draggable: true,
                        removable: true,
                        editable: true,
                        droppable: true,
                        highlightable: true
                    },
                    {
                        type: 'image',
                        attributes: { class: 'card-img-top w-100' },
                        removable: true,
                        draggable: true,
                        droppable: false,
                        highlightable: true
                    },
                    {
                        type: 'text',
                        tagName: 'div',
                        name: 'Card body',
                        attributes: { class: 'card-body' },
                        draggable: false,
                        removable: false,
                        editable: true,
                        droppable: true,
                        highlightable: true,
                        components: [
                            {
                                type: 'text',
                                tagName: 'h5',
                                name: 'Card title',
                                attributes: { class: 'card-title' },
                                content: 'Card Title',
                                draggable: true,
                                removable: true,
                                editable: true,
                                droppable: true,
                                highlightable: true
                            },
                            {
                                type: 'text',
                                tagName: 'h6',
                                name: 'Card subtitle',
                                attributes: { class: 'card-subtitle mb-2 text-muted' },
                                content: 'Card Subtitle',
                                draggable: true,
                                removable: true,
                                editable: true,
                                droppable: true,
                                highlightable: true
                            },
                            {
                                type: 'text',
                                tagName: 'div',
                                name: 'Card text',
                                attributes: { class: 'card-text mb-2' },
                                content: 'Some quick example text to build on the card title and make up the bulk of the card\'s content.',
                                draggable: true,
                                removable: true,
                                editable: true,
                                droppable: true,
                                highlightable: true
                            },
                            {
                                type: 'link',
                                name: 'Card button primary',
                                attributes: { class: 'btn btn-primary card-link' },
                                content: 'Card Link',
                                draggable: true,
                                removable: true,
                                editable: true,
                                droppable: false,
                                highlightable: true
                            },
                            {
                                type: 'link',
                                name: 'Card button',
                                attributes: { class: 'card-link' },
                                content: 'Another Link',
                                draggable: true,
                                removable: true,
                                editable: true,
                                droppable: false,
                                highlightable: true
                            }
                        ]
                    },
                    {
                        type: 'text',
                        tagName: 'div',
                        name: 'Card footer',
                        attributes: { class: 'card-footer text-muted' },
                        content: 'Card Footer',
                        draggable: true,
                        removable: true,
                        editable: true,
                        droppable: true,
                        highlightable: true
                    }
                ],
            },

            init() {
                this.listenTo(this, 'change:cardType', this.updateCardStyle);
                this.listenTo(this, 'change:imagePosition', this.updateImagePosition);
                this.listenTo(this, 'change:imageUrl', this.updateImageSource);
                this.listenTo(this, 'change:showHeader', this.toggleHeader);
                this.listenTo(this, 'change:showFooter', this.toggleFooter);
            },

            updateCardStyle() {
                const type = this.get('cardType');
                const prevType = this.previous('cardType');

                // Remove previous style classes
                if (prevType && prevType !== 'default') {
                    this.removeClass(`bg-${prevType}`);
                    this.removeClass(`border-${prevType}`);
                    this.removeClass(`text-white`);
                    this.removeClass(`text-dark`);
                }

                // Add new style classes
                if (type !== 'default') {
                    this.addClass(`bg-${type}`, `border-${type}`);

                    // Set appropriate text color
                    if (type === 'light') {
                        this.addClass('text-dark');
                    } else if (['dark', 'primary', 'secondary'].includes(type)) {
                        this.addClass('text-white');
                    }
                }

                // Update button styles
                const buttons = this.find('a.btn');
                buttons.forEach(btn => {
                    btn.removeClass(`btn-${prevType}`);
                    btn.addClass(`btn-${type === 'default' ? 'primary' : type === 'light' ? 'dark' : type}`);
                });
            },

            updateImagePosition() {
                const position = this.get('imagePosition');
                const image = this.find('img')[0];

                if (image) {
                    image.removeClass('card-img-top card-img-bottom');
                    image.addClass(`card-img-${position}`);

                    // Move image to correct position
                    const header = this.find('.card-header')[0];
                    const body = this.find('.card-body')[0];
                    const footer = this.find('.card-footer')[0];

                    if (position === 'top') {
                        if (header) {
                            image.moveAfter(header);
                        } else {
                            image.moveToTop();
                        }
                    } else {
                        if (footer) {
                            image.moveBefore(footer);
                        } else if (body) {
                            image.moveAfter(body);
                        } else {
                            image.moveToBottom();
                        }
                    }
                }
            },

            updateImageSource() {
                const imageUrl = this.get('imageUrl');
                const image = this.find('img')[0];

                if (imageUrl) {
                    if (!image) {
                        // Create new image
                        const newImage = {
                            type: 'image',
                            attributes: {
                                class: `card-img-${this.get('imagePosition')}`,
                                src: imageUrl
                            },
                            removable: true,
                            draggable: true
                        };

                        // Insert at correct position
                        if (this.get('imagePosition') === 'top') {
                            const header = this.find('.card-header')[0];
                            if (header) {
                                this.append(newImage, { at: header.index() + 1 });
                            } else {
                                this.append(newImage, { at: 0 });
                            }
                        } else {
                            const body = this.find('.card-body')[0];
                            if (body) {
                                this.append(newImage, { at: body.index() + 1 });
                            } else {
                                this.append(newImage);
                            }
                        }
                    } else {
                        // Update existing image
                        image.addAttributes({ src: imageUrl });
                    }
                } else if (image) {
                    // Remove image if URL is empty
                    image.remove();
                }
            },

            toggleHeader() {
                const showHeader = this.get('showHeader');
                const header = this.find('.card-header')[0];

                if (showHeader && !header) {
                    // Add header at top
                    this.append({
                        type: 'text',
                        tagName: 'div',
                        name: 'Card header',
                        attributes: { class: 'card-header' },
                        content: 'Card Header',
                        draggable: true,
                        removable: true,
                        editable: true
                    }, { at: 0 });
                } else if (!showHeader && header) {
                    // Remove header
                    header.remove();
                }
            },

            toggleFooter() {
                const showFooter = this.get('showFooter');
                const footer = this.find('.card-footer')[0];

                if (showFooter && !footer) {
                    // Add footer at bottom
                    this.append({
                        type: 'text',
                        tagName: 'div',
                        name: 'Card footer',
                        attributes: { class: 'card-footer text-muted' },
                        content: 'Card Footer',
                        draggable: true,
                        removable: true,
                        editable: true
                    });
                } else if (!showFooter && footer) {
                    // Remove footer
                    footer.remove();
                }
            }
        },

        view: {
            events: {
                dblclick: 'onActive'
            },

            init() {
                this.listenTo(this.model, 'change:cardType', this.updateButtonStyles);
            },

            updateButtonStyles() {
                const type = this.model.get('cardType');
                const buttons = this.el.querySelectorAll('a.btn');

                buttons.forEach(btn => {
                    // Remove all button color classes
                    btn.className = btn.className.replace(/\bbtn-\S+/g, '');

                    // Add appropriate button class
                    if (type === 'default') {
                        btn.classList.add('btn-primary');
                    } else if (type === 'light') {
                        btn.classList.add('btn-dark');
                    } else {
                        btn.classList.add(`btn-${type}`);
                    }
                });
            }
        }
    });

    // Add to blocks panel
    editor.Blocks.add("grapesjs-bootstrap-card", {
        label: "Card",
        category: "Bootstrap Component",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M64 32C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zm80 256h64c44.2 0 80 35.8 80 80c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16c0-44.2 35.8-80 80-80zm96-96c0 35.3-28.7 64-64 64s-64-28.7-64-64s28.7-64 64-64s64 28.7 64 64zm128-32H496c8.8 0 16 7.2 16 16s-7.2 16-16 16H368c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64H496c8.8 0 16 7.2 16 16s-7.2 16-16 16H368c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64H496c8.8 0 16 7.2 16 16s-7.2 16-16 16H368c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>`,
        content: { type: "grapesjs-bootstrap-card" },
        activate: true
    });
});