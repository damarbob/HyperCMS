<?php
$context = 'user:' . user_id();
$datatableEntriesPerPageValue = service('settings')->get('App.datatableEntriesPerPage', $context) ?: 10;
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<table id="hyperTable" class="table is-striped" style="width:100%">
</table>

<!-- Content modal -->
<div id="contentModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card is-fullscreen">
        <section class="modal-card-body">
            <h1 class="title"><?= lang('Admin.preview') ?></h1>
            <pre id="contentArea"></pre>
        </section>
    </div>
    <button class="modal-close button is-large" aria-label="close"></button>
</div>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.css" rel="stylesheet" integrity="sha384-wAbr9qEp5JojSKDr01s3gfk2usG6WR/OfpUIFEliYPzIBy5Jr9WBChdyqfWfbtt6" crossorigin="anonymous">

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.js" integrity="sha384-JYvoIYf/4ra9ifw1ESGWSNm3QVSdAuT8OaSDJLTKTkRWntshpsM1beOZKdjAXOAb" crossorigin="anonymous"></script>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>

<!-- HTML Entity -->
<script src="https://cdn.jsdelivr.net/npm/he@1.2.0/he.min.js"></script>

<script>
    function confirmSelectedData() {
        var selectedRows = hyperTable.rows({
            selected: true
        }).data().toArray();
        if (selectedRows.length > 0) {
            // Post the message with the deserialized data included
            window.parent.postMessage({
                action: 'modelDataSelected',
                data: selectedRows
            }, '<?= base_url() ?>');
        }
    }

    function openPreviewModal(content) {
        // Set the content in the modal's content area
        const contentArea = document.getElementById('contentArea');
        if (contentArea) {
            contentArea.innerHTML = he.encode(unescape(content));
        }
        // Get the modal element (you can also pass this in if desired)
        const modal = document.getElementById('contentModal');
        // Now open the modal using your openModal function (make sure it's defined)
        openModal(modal);
    }
</script>

<script type="text/javascript">
    /* Configs */

    var lang = '<?= $lang ?>'; // Get lang from the backend

    // CSRF
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';

    // States
    var isTrash = true; // Set to true to show trashed models.

    // DataTables options
    var options = {
        processing: true,
        serverSide: true,

        // Page length options taken from settings.
        // This is the default value for the page length dropdown.
        pageLength: <?= $datatableEntriesPerPageValue ?>,

        // Configure the AJAX endpoint and method.
        ajax: {
            url: "<?= base_url('/api/v1/model-data') ?>", // Our CI4 API endpoint that returns JSON
            type: "POST", // Often POST is used for server side processing
            data: function(d) {
                d.id = <?= $model['id'] ?>;
            }
        },

        // Define the columns based on our "models" table data.
        // Adjust the rendering if we wish to, for example, stringify JSON fields.
        columns: [{
                title: "<?= lang("Admin.id") ?>",
                data: "data_id",
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
                    return renderFieldTags(data);
                },
                createdCell: function(cell) {
                    // Initialize tooltips for this cell
                    $(cell).find('.field-tag').each(function() {
                        tippy(this, {
                            allowHTML: true,
                            interactive: true,
                            placement: 'top-start',
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
                title: "<?= lang("Admin.dateCreated") ?>",
                data: "date_created",
                orderSequence: ["asc", "desc"],
            },
        ],

        columnDefs: [{
            type: 'date',
            targets: [4]
        }],

        // Layout
        layout: {
            topStart: {
                buttons: {
                    buttons: [{
                            // Use data button
                            extend: "selected",
                            text: '<span class="icon"><i class="fa-solid fa-check"></i></span><span><?= lang('Admin.useData') ?></span>',
                            className: 'is-primary is-in-iframe',
                            action: function(e, dt, node, config) {
                                window.hyper_swal.confirm({
                                    text: "<?= lang('Admin.thisActionWillOverwriteCurrentInput') ?>",
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        confirmSelectedData();
                                    }
                                });
                            }
                        },
                        {
                            extend: "colvis", // Column visibility button
                            text: '<i class="fa-solid fa-table"></i>',
                            titleAttr: '<?= lang('Admin.data') ?>',
                        },
                        {
                            extend: "excelHtml5", // Export to Excel using HTML5 features
                            text: '<i class="fa-solid fa-download"></i>',
                            titleAttr: '<?= lang('Admin.excel') ?>',
                        },
                        {
                            extend: "print", // Print button
                            text: '<i class="fa-solid fa-print"></i>',
                            titleAttr: '<?= lang('Admin.print') ?>',
                        },
                        {
                            // Refresh button
                            text: '<i class="fa-solid fa-arrows-rotate"></i>',
                            titleAttr: '<?= lang('Admin.refresh') ?>',
                            action: function(e, dt, node, config) {
                                dt.ajax.reload(function() {
                                    window.hyper_swal.success('<?= lang('Admin.successfullyRefreshed') ?>');
                                });
                            }
                        },
                        {
                            // Clear history button
                            text: '<i class="fa-solid fa-trash"></i>',
                            className: 'is-danger',
                            titleAttr: '<?= lang('Admin.clearHistory') ?>',
                            action: function(e, dt, node, config) {
                                window.hyper_swal.confirm({
                                    text: "<?= lang('Admin.thisActionPermanentlyDeleteAllHistorical') ?>",
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // AJAX request to clear model history
                                        $.ajax({
                                            url: '<?= base_url('admin/model-data/clear-history/' . $model['id']) ?>', // Adjust this URL as needed.
                                            type: 'POST',
                                            data: {
                                                [csrfName]: csrfHash
                                            }, // Include CSRF token for security
                                            dataType: 'json', // Expecting JSON response from the server (if you modify your backend to return JSON)
                                            success: function(response) {
                                                window.hyper_swal.success(response.success);
                                                dt.ajax.reload();
                                            },
                                            error: function(xhr, status, error) {
                                                // Handle errors here
                                                window.hyper_swal.error(error);
                                            }
                                        });
                                    }
                                });
                            }
                        },
                    ],
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
            // Add double-click event to open preview modal
            $(row).on('dblclick', function() {
                // Get the ID from the data
                var id = data.id;

                // Open preview modal
                openPreviewModal(data.fields);
            });
        },

        // Enable additional DataTables plugins
        colReorder: true, // Allow column reordering
        fixedHeader: true, // Keep header fixed as we scroll
        responsive: true, // Make the table responsive on various devices
        select: true, // Allow row selection
    };

    // Order descending by date_created (last column). Assuming last column is always 'date_created' column.
    // @IMPORTANT: Changing the last column will require changing the index below regardless of column visibility (probably).
    options.order = [
        [options.columns.length - 1, "desc"]
    ];

    // DataTables language
    // Add language option only when locale is not 'en'
    if (lang !== 'en') {
        var languageUrl;

        // Determine which language file to use (example for Indonesian)
        switch (lang) {
            case 'id':
                languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/id.json";
                break;
                // We can add cases here for other locales if needed
            default:
                // Fallback (or choose not to override default English)
                languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/en-GB.json";
        }

        // Add the language configuration into the options
        options.language = {
            url: languageUrl
        };
    }

    /* End of configs */

    /* Init */

    var hyperTable = new DataTable('#hyperTable', options);

    toggleTrashView(); // Initialize the trash view based on the default value

    /* End of init */

    /* Views */

    // Function to toggle the trash view
    // and update the button visibility accordingly
    function toggleTrashView() {
        isTrash = !isTrash; // Toggle the trash view
        hyperTable.ajax.reload(); // Reload the table data

        if (isTrash) {
            $('button.hyper-new').hide(250);
            $('button.hyper-delete').hide(250);
            $('button.hyper-restore').show(250);
        } else {
            $('button.hyper-new').show(250);
            $('button.hyper-delete').show(250);
            $('button.hyper-restore').hide(250);;
        }
    }

    // Render colorful tags for the model fields
    function renderFieldTags(data) {
        // Parse the data if it's a string
        if (typeof data === "string") {
            try {
                data = JSON.parse(data);
            } catch (err) {
                return '<span class="tag is-light"><?= lang('Admin.invalidJson') ?></span>';
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
    }

    /* End of views */

    /* Requests */

    // AJAX request to delete models (POSTing the ids array)
    function deleteModels(ids) {
        $.ajax({
            url: '<?= base_url('admin/models/delete') ?>',
            type: 'POST',
            data: {
                ids: ids,
                [csrfName]: csrfHash
            }, // Include CSRF token for security
            dataType: 'json', // Expecting JSON response from the server
            success: function(response) {
                window.hyper_swal.success(response.success, {
                    showConfirmButton: true,
                    confirmButtonText: "<?= lang('Admin.undo') ?>",
                }).then((result) => {
                    if (result.isConfirmed) {
                        restoreModels(ids);
                    }
                });
                hyperTable.ajax.reload();
            },
            error: function(xhr, status, error) {
                // Handle errors
                window.hyper_swal.error(error);
            }
        });
    }

    // AJAX request to restore models (POSTing the ids array)
    function restoreModels(ids) {
        $.ajax({
            url: '<?= base_url('admin/models/restore') ?>',
            type: 'POST',
            data: {
                ids: ids,
                [csrfName]: csrfHash
            }, // Include CSRF token for security
            dataType: 'json', // Expecting JSON response from the server
            success: function(response) {
                window.hyper_swal.success(response.success);
                hyperTable.ajax.reload();
            },
            error: function(xhr, status, error) {
                // Handle errors
                window.hyper_swal.error(error);
            }
        });
    }

    /* End of requests */
</script>
<?= $this->endSection() ?>