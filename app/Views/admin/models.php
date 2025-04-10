<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<table id="hyperTable" class="table is-striped" style="width:100%">
</table>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.css" rel="stylesheet" integrity="sha384-wAbr9qEp5JojSKDr01s3gfk2usG6WR/OfpUIFEliYPzIBy5Jr9WBChdyqfWfbtt6" crossorigin="anonymous">

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.js" integrity="sha384-JYvoIYf/4ra9ifw1ESGWSNm3QVSdAuT8OaSDJLTKTkRWntshpsM1beOZKdjAXOAb" crossorigin="anonymous"></script>
<style>
    /* (Optional) Adjust tooltip styling if needed */
    .has-tooltip-multiline[data-tooltip]::after {
        white-space: pre-wrap;
    }
</style>
<script>
    // Unique id generator for popovers and nested containers.
    let uid = 0;

    /**
     * Toggles the display of a nested container (for nested tags)
     * and switches the button text between plus ("+") and minus ("−").
     * @param {string} containerId - The id of the container to toggle.
     * @param {string} buttonId - The id of the toggle button.
     */
    function toggleNested(containerId, buttonId) {
        const container = document.getElementById(containerId);
        const btn = document.getElementById(buttonId);
        if (!container || !btn) return;
        if (container.style.display === "none" || container.style.display === "") {
            container.style.display = "inline-block";
            btn.textContent = "−";
        } else {
            container.style.display = "none";
            btn.textContent = "+";
        }
    }

    /**
     * Toggles the visibility of a popover that shows the full content.
     * @param {string} popoverId - The id of the popover element.
     */
    function togglePopover(popoverId) {
        const popover = document.getElementById(popoverId);
        if (!popover) return;
        if (popover.style.display === "none" || popover.style.display === "") {
            popover.style.display = "block";
        } else {
            popover.style.display = "none";
        }
    }

    /**
     * Recursively renders an object as a series of Bulma tags.
     * For each property, it creates a tag with text "key: value".
     * If the text exceeds a given threshold, it will be truncated and
     * an info-popover is attached (click to toggle) showing the full text.
     * If the value is a nested object, an expand/shrink button toggles its display.
     *
     * @param {object} obj - The object to render.
     * @param {object} [opts] - Options for rendering.
     * @param {Array} [opts.colors] - An array of Bulma tag color classes.
     *                   Default: ['is-primary', 'is-link', 'is-info', 'is-success'].
     * @param {number} [opts.threshold] - Maximum characters for each tag.
     *                   Default: 30.
     * @param {string} [opts.indent] - HTML to prepend to every tag (for nested indentation).
     *                   Default: ''.
     * @returns {string} - The HTML string that represents the tags.
     */
    function renderBulmaTagsForObject(obj, opts = {}) {
        const defaultColors = ['is-primary', 'is-link', 'is-info', 'is-success'];
        const colors = opts.colors || defaultColors;
        const threshold = opts.threshold || 30;
        const indent = opts.indent || '';
        let output = '';
        let i = 0;

        for (let key in obj) {
            if (!Object.prototype.hasOwnProperty.call(obj, key)) continue;

            let rawValue = obj[key];
            let fullText;
            // For primitives, combine key and value; for objects, use key only in the parent tag.
            if (typeof rawValue === 'object' && rawValue !== null) {
                fullText = key;
            } else {
                fullText = key + ": " + String(rawValue);
            }

            // Determine display text and if it must be truncated.
            let displayText = fullText;
            let isTruncated = false;
            if (fullText.length > threshold) {
                displayText = fullText.substring(0, threshold - 3) + "...";
                isTruncated = true;
            }

            // Choose a tag color from the current sequence.
            let color = colors[i % colors.length];
            let currentHtml = '';
            // Generate a unique id for popovers or nested containers.
            let currentUid = "uid-" + uid++;

            if (typeof rawValue === 'object' && rawValue !== null) {
                // Render the parent tag (showing only the key).
                currentHtml += `${indent}<span class="tag ${color}" style="margin-right:5px; margin-bottom:5px;">${key}:</span> `;

                // Add an expand button to toggle children display.
                let nestedContainerId = "nest-" + uid++;
                let toggleButtonId = "toggle-" + uid++;
                currentHtml += `<button id="${toggleButtonId}" class="button is-small" style="margin-right:5px;" onclick="toggleNested('${nestedContainerId}', '${toggleButtonId}')">+</button>`;

                // Render the nested object inside a container that is hidden by default.
                currentHtml += `<div id="${nestedContainerId}" style="display:none; margin-left:10px; margin-bottom:5px;">`;
                // For nested objects, if the parent's color was "is-primary", exclude it from children.
                let childColors = (color === 'is-primary') ? colors.filter(c => c !== 'is-primary') : colors;
                if (childColors.length === 0) childColors = colors;
                currentHtml += renderBulmaTagsForObject(rawValue, {
                    colors: childColors,
                    threshold: threshold,
                    indent: ''
                });
                currentHtml += `</div>`;
            } else {
                // For primitive values, render a single tag.
                currentHtml += `${indent}<button class="button is-small ${color}" style="margin-right:5px; margin-bottom:5px; cursor:pointer;"`;
                // If truncated, add an onclick handler that toggles a popover.
                if (isTruncated) {
                    let popoverId = "popover-" + uid++;
                    currentHtml += ` onclick="togglePopover('${popoverId}')"`;
                    currentHtml += `>${displayText}</button>`;
                    // The popover element (positioned absolutely); you might adjust styling as needed.
                    currentHtml += `<div id="${popoverId}" class="box" style="display:none; position:absolute; z-index:100; background:white; border:1px solid #dbdbdb; padding:5px;">${fullText}</div>`;
                } else {
                    currentHtml += `>${displayText}</span>`;
                }
            }

            output += currentHtml + " ";
            i++;
        }

        return output;
    }
</script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="text/javascript">
    var lang = '<?= $lang ?>';
    var options = {
        processing: true,
        serverSide: true,

        // Configure the AJAX endpoint and method.
        ajax: {
            url: "<?= base_url('/api/test/models/dt') ?>", // Your CI4 API endpoint that returns JSON, @TODO: remove test
            type: "POST", // Often POST is used for server side processing
        },

        // Define the columns based on your "models" table data.
        // Adjust the rendering if you wish to, for example, stringify JSON fields.
        columns: [{
                title: "<?= lang("Admin.id") ?>",
                data: "id",
                visible: false,
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.name") ?>",
                data: "name",
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang('Admin.fields') ?>",
                data: "fields",
                orderSequence: ["asc", "desc"],
                render: (data) => {
                    // Parse the data if it's a string
                    if (typeof data === "string") {
                        try {
                            data = JSON.parse(data);
                        } catch (err) {
                            return '<span class="tag is-light">Invalid JSON</span>';
                        }
                    }

                    // Return if data is not an object/array
                    if (typeof data !== "object" || data === null) {
                        return data;
                    }

                    // Define color classes for tags
                    const tagClasses = ['is-primary', 'is-link', 'is-info', 'is-success', 'is-warning', 'is-danger'];
                    let output = '<div class="tags are-small" style="margin-bottom: 0;">';
                    let index = 0;

                    // Process each field
                    if (Array.isArray(data)) {
                        data.forEach((field) => {
                            if (field && typeof field === 'object') {
                                const tagClass = tagClasses[index % tagClasses.length];
                                const fieldLabel = field.label || field.id || `Field ${index}`;
                                const fieldType = field.type ? `Type: ${field.type}` : '';
                                const fieldHelper = field.helper ? `Help: ${field.helper}` : '';

                                // Create tooltip content
                                const tooltipContent = `
                                    <div class="content" style="text-align: left; max-width: 300px;">
                                        <p><strong>${fieldLabel}</strong></p>
                                        ${fieldType ? `<p>${fieldType}</p>` : ''}
                                        ${fieldHelper ? `<p>${fieldHelper}</p>` : ''}
                                        ${field.className ? `<p>Class: ${field.className}</p>` : ''}
                                    </div>
                                `;

                                // Create the tag with tooltip attributes
                                output += `
                                    <span class="tag ${tagClass} field-tag" 
                                        data-tippy-content="${tooltipContent.replace(/"/g, '&quot;')}"
                                        style="margin-right: 5px; margin-bottom: 5px; cursor: help;">
                                        ${fieldLabel}
                                    </span>
                                `;
                                index++;
                            }
                        });
                    } else {
                        // Handle object case if needed
                        for (const key in data) {
                            if (data.hasOwnProperty(key)) {
                                const tagClass = tagClasses[index % tagClasses.length];
                                const value = typeof data[key] === 'object' ?
                                    JSON.stringify(data[key]) :
                                    String(data[key]);

                                output += `
                                    <span class="tag ${tagClass}" 
                                        style="margin-right: 5px; margin-bottom: 5px;">
                                        ${key}: ${value.length > 30 ? value.substring(0, 27) + '...' : value}
                                    </span>
                                `;
                                index++;
                            }
                        }
                    }

                    output += '</div>';
                    return output;
                },
                createdCell: function(cell) {
                    // Initialize tooltips for this cell
                    $(cell).find('.field-tag').each(function() {
                        tippy(this, {
                            allowHTML: true,
                            interactive: true,
                            placement: 'top-start',
                            theme: 'light',
                            animation: 'shift-away',
                            delay: [100, 0],
                        });
                    });
                },
            },
            {
                title: "<?= lang("Admin.createdBy") ?>",
                data: "created_by",
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.editedBy") ?>",
                data: "edited_by",
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.createdAt") ?>",
                data: "created_at",
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.dateModified") ?>",
                data: "date_modified",
                orderSequence: ["asc", "desc"],
            },
        ],

        columnDefs: [{
            type: 'date',
            targets: [4, 5]
        }],

        // Layout
        layout: {
            topStart: {
                buttons: {
                    buttons: [{
                            className: 'is-primary',
                            text: '<i class="fa-solid fa-plus mr-2"></i><?= lang('Admin.new') ?>',
                            action: function(e, dt, node, config) {
                                window.location.href = '<?= $uri . 'new' ?>';
                            }
                        },
                        {
                            extend: "colvis", // Column visibility button
                            text: '<i class="fa-solid fa-table mr-2"></i><?= lang('Admin.data') ?>',
                        },
                        {
                            extend: "excelHtml5", // Export to Excel using HTML5 features
                            text: '<i class="fa-solid fa-download mr-2"></i><?= lang('Admin.excel') ?>',
                        },
                        {
                            extend: "print", // Print button
                            text: '<i class="fa-solid fa-print mr-2"></i><?= lang('Admin.print') ?>',
                        },
                    ]
                },
            },
            topEnd: {
                pageLength: {
                    menu: [10, 25, 50, 100],
                },
                search: {
                    placeholder: "<?= lang('Admin.search') ?>",
                    text: "_INPUT_",
                },
            },
            bottomEnd: {
                paging: {
                    numbers: true,
                },
            },
        },

        rowCallback: function(row, data, index) {
            // Add double-click event to navigate to Edit page
            $(row).on('dblclick', function() {
                // Get the ID from the data
                var id = data.id;

                // Navigate to the Edit page
                window.location.href = "<?= $uri ?>" + id + "/edit";
            });
        },

        // Enable additional DataTables plugins
        colReorder: true, // Allow column reordering
        fixedHeader: true, // Keep header fixed as you scroll
        responsive: true, // Make the table responsive on various devices
        select: true, // Allow row selection
    };

    // Order descending by date_modified (last column). Assuming last column is always 'date_modified' column.
    // @IMPORTANT: Changing the last column will require changing the index below regardless of column visibility (probably).
    options.order = [
        [options.columns.length - 1, "desc"]
    ];

    // Add language option only when locale is not 'en'
    if (lang !== 'en') {
        var languageUrl;

        // Determine which language file to use (example for Indonesian)
        switch (lang) {
            case 'id':
                languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/id.json";
                break;
                // You can add cases here for other locales if needed
            default:
                // Fallback (or choose not to override default English)
                languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/en-GB.json";
        }

        // Add the language configuration into the options
        options.language = {
            url: languageUrl
        };
    }
    var hyperTable = new DataTable('#hyperTable', options);
</script>
<?= $this->endSection() ?>