<?php
$context = 'user:' . user_id();
$datatableEntriesPerPageValue = service('settings')->get('App.datatableEntriesPerPage', $context) ?: 10;
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<table id="hyperTable" class="table is-striped" style="width:100%">
</table>

<!-- New entry modal -->
<div id="newModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon mr-4">
                    <i class="fa-solid fa-plus"></i>
                </span>
                <span>
                    <?= lang('Admin.newEntry') ?>
                </span>
            </p>
            <button class="delete" aria-label="close"></button>
        </header>
        <section class="modal-card-body">
            <div id="users">
                <div class="field has-addons">
                    <div class="control">
                        <input class="search input" type="text" placeholder="<?= lang('Admin.search') ?>">
                    </div>
                    <div class="control">
                        <button class="sort button" data-sort="model-name">
                            <?= lang('Admin.sortByName') ?>
                        </button>
                    </div>
                </div>
                <div class="menu">
                    <ul class="list menu-list">
                        <?php foreach ($models as $model) : ?>
                            <li>
                                <a href="<?= $uri . 'new?model_id=' . $model['id'] ?>">
                                    <span class="icon">
                                        <i class="<?= !empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open' ?>"></i>
                                    </span>
                                    <span class="model-name">
                                        <?= lang('Admin.newx', ['x' => $model['name']]) ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    </ul>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <div class="buttons">
                <button class="button"><?= lang('Admin.cancel') ?></button>
            </div>
        </footer>
    </div>
</div>

<!-- Filter modal -->
<div id="filterModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon mr-4">
                    <i class="fa-solid fa-filter"></i>
                </span>
                <span>
                    <?= lang('Admin.filter') ?>
                </span>
            </p>
            <!-- The delete button has a "modal-close" class so that it can be closed via JS -->
            <button class="delete" aria-label="close"></button>
        </header>
        <section class="modal-card-body">
            <div id="filterModels">
                <h2 class="subtitle"><?= lang('Admin.models') ?></h2>
                <div class="buttons">
                    <!-- A button to remove filtering and show "all" models -->
                    <button type="button" class="button is-light filter-model-reset">
                        <?= lang('Admin.all') ?>
                    </button>
                    <?php foreach ($models as $model): ?>
                        <button type="button"
                            class="button filter-model-btn"
                            data-model-id="<?= $model['id'] ?>">
                            <span class="icon">
                                <i class="<?= !empty($model['icon']) ? $model['icon'] : 'fa-solid fa-box-open' ?>"></i>
                            </span>
                            <span>
                                <?= $model['name'] ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button type="button" class="button"><?= lang('Admin.cancel') ?></button>
        </footer>
    </div>
</div>


<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.css" rel="stylesheet" integrity="sha384-wAbr9qEp5JojSKDr01s3gfk2usG6WR/OfpUIFEliYPzIBy5Jr9WBChdyqfWfbtt6" crossorigin="anonymous">

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.js" integrity="sha384-JYvoIYf/4ra9ifw1ESGWSNm3QVSdAuT8OaSDJLTKTkRWntshpsM1beOZKdjAXOAb" crossorigin="anonymous"></script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>

<!-- HTML Entity -->
<script src="https://cdn.jsdelivr.net/npm/he@1.2.0/he.min.js"></script>

<script type="text/javascript">
    /* Configs */
    var lang = '<?= $lang ?>'; // Get lang from the backend

    // CSRF
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';

    // States
    var activeModelFilter = null; // Hold the filter model_id; null means no filter
    var isTrash = true; // Set to true to show trashed entries.

    // DataTables options
    // Modify our DataTable options to supply additional data to the AJAX request:
    var options = {
        processing: true,
        serverSide: true,

        pageLength: <?= $datatableEntriesPerPageValue ?>,

        ajax: {
            url: "<?= base_url('/api/test/entries/dt') ?>",
            type: "POST",
            data: function(d) {

                // Whether trash view is enabled
                d.trash = isTrash;

                // Include the filter model_id if it is active.
                if (activeModelFilter) {
                    d.model_id = activeModelFilter;
                }
            }
        },

        columns: [{
                title: "<?= lang("Admin.id") ?>",
                data: "id",
                visible: false,
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.modelId") ?>",
                data: "model_id",
                visible: false,
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.model") ?>",
                data: "model_name",
                orderSequence: ["asc", "desc"],
            },
            {
                title: "<?= lang("Admin.fields") ?>",
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

        layout: {
            topStart: {
                buttons: [{
                        // New button
                        text: '<span class="icon"><i class="fa-solid fa-plus"></i></span><span><?= lang('Admin.new') ?></span>',
                        className: 'is-primary js-modal-trigger hyper-new',
                        attr: {
                            "data-target": "newModal"
                        },
                    },
                    {
                        // Empty trash button
                        text: '<span class="icon"><i class="fa-solid fa-face-tired"></i></span><span><?= lang('Admin.emptyTrash') ?></span>',
                        className: 'is-danger hyper-purge-deleted',
                        action: function(e, dt, node, config) {
                            // Confirm empty trash
                            window.hyper_swal.confirm().then((result) => {
                                if (result.isConfirmed) {
                                    emptyTrash();
                                }
                            });
                        }
                    },
                    {
                        // Filter button
                        text: '<span class="icon"><i class="fa-solid fa-filter"></i></span><span><?= lang('Admin.filter') ?></span>',
                        className: 'is-info js-modal-trigger',
                        attr: {
                            "data-target": "filterModal"
                        },
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
                        // Toggle trash view button
                        text: '<span class="icon"><i class="fa-solid fa-recycle"></i></span><span><?= lang('Admin.trash') ?></span>',
                        className: 'is-warning',
                        action: function(e, dt, node, config) {
                            toggleTrashView(); // Call the function to toggle trash view

                            if (isTrash) {
                                $(node).addClass('is-active');
                                $(node).html('<span class="icon"><i class="fa-solid fa-xmark"></i></span><span><?= lang('Admin.exit') ?></span>');
                            } else {
                                $(node).removeClass('is-active');
                                $(node).html('<span class="icon"><i class="fa-solid fa-recycle"></i></span><span><?= lang('Admin.trash') ?></span>');
                            }
                        }
                    },
                    {
                        // Delete button
                        extend: "selected",
                        text: '<i class="fa-solid fa-trash"></i>',
                        titleAttr: '<?= lang('Admin.delete') ?>',
                        className: 'is-danger hyper-delete',
                        action: function(e, dt, node, config) {
                            var selectedRows = dt.rows({
                                selected: true
                            }).data().toArray();
                            if (selectedRows.length > 0) {
                                // Map the selected rows into an array of IDs
                                var ids = selectedRows.map(function(row) {
                                    return row.id;
                                });

                                console.log('Delete IDs:', ids);

                                deleteEntries(ids);
                            } else {
                                window.hyper_swal.error('<?= lang('Admin.selectToDelete') ?>');
                            }
                        }
                    },
                    {
                        // Restore button
                        extend: "selected",
                        text: '<span class="icon"><i class="fa-solid fa-rotate-left"></i></span><span><?= lang('Admin.restore') ?></span>',
                        className: 'is-success hyper-restore',
                        action: function(e, dt, node, config) {
                            var selectedRows = dt.rows({
                                selected: true
                            }).data().toArray();
                            if (selectedRows.length > 0) {
                                // Map the selected rows into an array of IDs
                                var ids = selectedRows.map(function(row) {
                                    return row.id;
                                });

                                console.log('Restore IDs:', ids);

                                restoreEntries(ids);
                            } else {
                                window.hyper_swal.error('<?= lang('Admin.selectToRestore') ?>');
                            }
                        }
                    },
                ],
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
            $(row).on('dblclick', function() {
                var id = data.id;
                window.location.href = "<?= base_url('admin/entries/') ?>" + id + "/edit";
            });
        },

        // Additional DataTable plugins
        colReorder: true,
        fixedHeader: true,
        responsive: true,
        select: true,
    };

    // Order descending by date_modified (last column). Assuming last column is always 'date_modified' column.
    options.order = [
        [options.columns.length - 1, "desc"]
    ];

    // DataTables language
    if (lang !== 'en') {
        var languageUrl;
        switch (lang) {
            case 'id':
                languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/id.json";
                break;
            default:
                languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/en-GB.json";
        }
        options.language = {
            url: languageUrl
        };
    }

    /* End of configs */

    /* Init */

    // Initialize our DataTable (assuming our table has the ID "hyperTable")
    var hyperTable = new DataTable('#hyperTable', options);

    toggleTrashView(); // Initialize the trash view based on the default value

    /* End of init */

    /* Views */

    // Modal & filter logic

    // Handle filter button clicks within the filter modal:
    $(document).on('click', '.filter-model-btn', function() {
        // Get the chosen model id
        activeModelFilter = $(this).data('model-id');

        // Toggle active state for UI feedback (using Bulma's "is-active" class)
        $('.filter-model-btn').removeClass('is-active');
        $(this).addClass('is-active');

        // Close the modal using our modal-close method
        closeModal(document.getElementById('filterModal'));
        // Reload the DataTable so the ajax call sends the new filter parameter.
        hyperTable.ajax.reload();
    });

    // Handle the "All" (reset) button:
    $(document).on('click', '.filter-model-reset', function() {
        activeModelFilter = null;
        $('.filter-model-btn').removeClass('is-active');
        closeModal(document.getElementById('filterModal'));
        hyperTable.ajax.reload();
    });

    // Function to toggle the trash view
    // and update the button visibility accordingly
    function toggleTrashView() {
        isTrash = !isTrash; // Toggle the trash view
        hyperTable.ajax.reload(); // Reload the table data

        if (isTrash) {
            $('button.hyper-new').hide(250);
            $('button.hyper-delete').hide(250);
            $('button.hyper-purge-deleted').show(250);
            $('button.hyper-restore').show(250);
        } else {
            $('button.hyper-new').show(250);
            $('button.hyper-delete').show(250);
            $('button.hyper-purge-deleted').hide(250);
            $('button.hyper-restore').hide(250);;
        }
    }

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
                    const fieldValue = field.value || '';

                    // Helper: encode and optionally truncate text
                    const formatText = (text, limit) =>
                        he.encode(text.length > limit ? text.substring(0, limit - 3) + '...' : text);

                    // Build tooltip content
                    const tooltipContent = `
                        <div class="content" style="text-align: left; max-width: 300px;">
                        <p><strong>${fieldLabel}</strong></p>
                        ${
                            fieldValue
                            ? `<p style="overflow: hidden">${formatText(String(fieldValue), 150)}</p>`
                            : ''
                        }
                        </div>
                    `;

                    // Build tag content
                    const tagContent = fieldValue ?
                        `<p style="overflow: hidden">${formatText(String(fieldValue), 20)}</p>` :
                        `(${fieldLabel})`;

                    // Append the tag with tooltip attributes to the output string.
                    output += `
                        <span class="tag ${tagClass} field-tag"
                        data-tippy-content="${he.encode(tooltipContent)}"
                        style="margin-right: 5px; margin-bottom: 5px; cursor: help;">
                            ${tagContent}
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

    function emptyTrash() {
        $.ajax({
            url: '<?= base_url('admin/entries/purge-deleted') ?>',
            type: 'POST',
            data: {
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

    // AJAX request to delete entries (POSTing the ids array)
    function deleteEntries(ids) {
        $.ajax({
            url: '<?= base_url('admin/entries/delete') ?>',
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
                        restoreEntries(ids);
                    }
                });
                hyperTable.ajax.reload();
            },
            error: function(xhr, status, error) {
                // Handle errors here
                window.hyper_swal.error(error);
            }
        });
    }

    // AJAX request to restore entries (POSTing the ids array)
    function restoreEntries(ids) {
        $.ajax({
            url: '<?= base_url('admin/entries/restore') ?>',
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
                // Handle errors here
                window.hyper_swal.error(error);
            }
        });
    }

    /* End of requests */
</script>
<?= $this->endSection() ?>