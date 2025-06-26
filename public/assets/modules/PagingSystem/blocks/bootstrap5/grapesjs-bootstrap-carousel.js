grapesjs.plugins.add("grapesjs-bootstrap-carousel", function (editor, opts = {}) {
    // Define the "carousel-indicators" component type
    editor.Components.addType("carousel-indicators", {
        model: {
            defaults: {
                tagName: "div",
                name: 'Carousel indicators',
                attributes: { class: "carousel-indicators" }
            }
        },
        view: {}
    });

    // Define the "carousel-inner" component type
    editor.Components.addType("carousel-inner", {
        model: {
            defaults: {
                tagName: "div",
                name: 'Carousel inner',
                attributes: { class: "carousel-inner" }
            }
        },
        view: {}
    });

    // Define the "carousel-control-prev" component type
    editor.Components.addType("carousel-control-prev", {
        model: {
            defaults: {
                tagName: "button",
                name: 'Carousel control prev',
                attributes: {
                    class: "carousel-control-prev",
                    type: "button",
                    // data-bs-target will be set in the main component to ensure uniqueness
                    "data-bs-slide": "prev"
                },
                components: [
                    {
                        tagName: "span",
                        name: 'Carousel control prev icon',
                        attributes: {
                            class: "carousel-control-prev-icon",
                            "aria-hidden": "true"
                        }
                    },
                    {
                        tagName: "span",
                        attributes: { class: "visually-hidden" },
                        components: "Previous"
                    }
                ]
            }
        },
        view: {}
    });

    // Define the "carousel-control-next" component type
    editor.Components.addType("carousel-control-next", {
        model: {
            defaults: {
                tagName: "button",
                name: 'Carousel control next',
                attributes: {
                    class: "carousel-control-next",
                    type: "button",
                    "data-bs-slide": "next"
                },
                components: [
                    {
                        tagName: "span",
                        name: 'Carousel control next icon',
                        attributes: {
                            class: "carousel-control-next-icon",
                            "aria-hidden": "true"
                        }
                    },
                    {
                        tagName: "span",
                        attributes: { class: "visually-hidden" },
                        components: "Next"
                    }
                ]
            }
        },
        view: {}
    });

    // Now define the main carousel component
    editor.Components.addType("bs-carousel", {
        model: {
            defaults: {
                tagName: "div",
                name: 'Carousel',
                attributes: {
                    id: `carousel-${Math.random().toString(36).substring(2, 9)}`, // consider generating unique IDs for multiple carousels
                    class: "carousel slide",
                    "data-bs-ride": "carousel"
                },
                draggable: true,
                droppable: false,
                traits: [
                    {
                        type: "number",
                        label: "Number of Slides",
                        name: "slideCount",
                        min: 1,
                        max: 10,
                        changeProp: true,
                        default: 3
                    },
                    {
                        type: "text",
                        label: "Interval (ms)",
                        name: "interval",
                        changeProp: true,
                        default: "5000"
                    }
                ],
                slideCount: 3,
                interval: "5000",
                components: [
                    {
                        type: "carousel-indicators",
                        components: [] // the indicators are populated dynamically
                    },
                    {
                        type: "carousel-inner",
                        editable: true,
                        components: [] // slides will be added here
                    },
                    {
                        type: "carousel-control-prev",
                    },
                    {
                        type: "carousel-control-next",

                    }
                ],
                //         styles: `
                //     .carousel {
                //       position: relative;
                //     }
                //     .carousel-inner {
                //       position: relative;
                //       overflow: hidden;
                //       width: 100%;
                //     }
                //     .carousel-item {
                //       position: relative;
                //       display: none;
                //       float: left;
                //       width: 100%;
                //       transition: transform 0.6s ease;
                //     }
                //     .carousel-item.active {
                //       display: block;
                //     }
                //   `
            },
            init() {
                // Trigger slide update when slideCount changes
                this.on("change:slideCount", this.updateSlides);
                this.updateSlides();
            },
            updateSlides() {
                const slideCount = parseInt(this.get("slideCount"), 10) || 0;

                // Get the children of the carousel component
                const indicators = this.findFirstType('carousel-indicators');
                const inner = this.findFirstType('carousel-inner');

                // Check if indicators and inner are found
                if (!indicators || !inner) return;

                // Clear existing indicators and slides
                this.components().forEach(child => {
                    if (child) {
                        if (child.is('carousel-indicators') || child.is('carousel-inner')) {
                            child.empty();
                        }
                    }
                });

                for (let i = 0; i < slideCount; i++) {
                    // Build each indicator button
                    let indicatorAttrs = {
                        type: "button",
                        "data-bs-target": `#${this.get('attributes').id}`,
                        "data-bs-slide-to": `${i}`,
                        "aria-label": `Slide ${i + 1}`
                    };
                    if (i === 0) {
                        indicatorAttrs.class = "active";
                        indicatorAttrs["aria-current"] = "true";
                    }
                    indicators.components().add({
                        tagName: "button",
                        attributes: indicatorAttrs,
                        components: []
                    });

                    // Build each carousel slide
                    inner.components().add({
                        tagName: "div",
                        name: `Slide ${i + 1}`,
                        attributes: {
                            class: i === 0 ? "carousel-item active" : "carousel-item"
                        },
                        components: [
                            {
                                tagName: "img",
                                type: "image",
                                attributes: {
                                    src: `https://placehold.co/800x400?text=Slide+${i + 1}`,
                                    class: "d-block w-100 img-fluid object-fit-cover",
                                    alt: `Slide ${i + 1}`
                                },
                                editable: true,
                                // style: { 'object-fit': 'cover' } // Ensure the image covers the slide area
                            }
                        ]
                    });

                    // Assign target to the carousel control buttons
                    const prevBtn = this.findType('carousel-control-prev')[0];
                    const nextBtn = this.findType('carousel-control-next')[0];
                    if (prevBtn) {
                        prevBtn.addAttributes({ "data-bs-target": `#${this.get('attributes').id}` });
                    }
                    if (nextBtn) {
                        nextBtn.addAttributes({ "data-bs-target": `#${this.get('attributes').id}` });
                    }
                }
            }
        },
        // Identification function for existing DOM elements
        isComponent(el) {
            if (el && el.classList && el.classList.contains("carousel") && el.classList.contains("slide")) {
                return { type: "bs-carousel" };
            }
        }
    });

    // Add the carousel component to the Blocks panel
    editor.Blocks.add("bs-carousel", {
        label: "Carousel",
        category: "Bootstrap Component",
        media: `<svg height="32px" width="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                <path d="M96 192h448a16 16 0 0 1 9.7 29.7l-224 192a16 16 0 0 1-19.4 0l-224-192A16 16 0 0 1 96 192z"/>
              </svg>`,
        content: { type: "bs-carousel" }
    });
});