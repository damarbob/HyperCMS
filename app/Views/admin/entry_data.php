<?php
$context = 'user:' . user_id();
$datatableEntriesPerPageValue = service('settings')->get('App.datatableEntriesPerPage', $context) ?: 10;
?>
<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <table id="hyperTable" class="table is-striped" style="width:100%">
    </table>
</div>

<!-- Content modal -->
<div id="contentModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card is-fullscreen">
        <section class="modal-card-body">
            <h1 class="title"><?= lang('Admin.preview') ?></h1>
            <pre id="contentModalTextarea"></pre>
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
<style>
    /* (Optional) Adjust tooltip styling if needed */
    .has-tooltip-multiline[data-tooltip]::after {
        white-space: pre-wrap;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>

<!-- HTML Entity -->
<script src="https://cdn.jsdelivr.net/npm/he@1.2.0/he.min.js"></script>

<script type="text/javascript">
    function confirmSelectedData() {
        var selectedRows = hyperTable.rows({
            selected: true
        }).data().toArray();
        if (selectedRows.length > 0) {
            // Post the message with the deserialized data included
            window.parent.postMessage({
                action: 'entryDataSelected',
                data: selectedRows
            }, '<?= base_url() ?>');
        }
    }

    function openContentModal(buttonEl) {
        // Retrieve the content from the button's data-content attribute
        const content = buttonEl.getAttribute('data-content');
        // Set the content in the modal's textarea
        const textarea = document.getElementById('contentModalTextarea');
        if (textarea) {
            textarea.innerHTML = he.encode(unescape(content));
        }
        // Get the modal element (you can also pass this in if desired)
        const modal = document.getElementById('contentModal');
        // Now open the modal using your openModal function (make sure it's defined)
        openModal(modal);
    }
</script>
<script type="text/javascript">
    /* Configs */

    var lang = '<?= $lang ?>';

    // CSRF
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';

    var options = {
        processing: true,
        serverSide: true,

        pageLength: <?= $datatableEntriesPerPageValue ?>,

        // Configure the AJAX endpoint and method.
        ajax: {
            url: "<?= base_url('/api/v1/entry-data') ?>",
            type: "POST",
            data: function(d) {
                d.id = <?= $entry['id'] ?>;
            }
        },

        // Define the columns based on your "models" table data.
        // Adjust the rendering if you wish to, for example, stringify JSON fields.
        columns: [
            <?php foreach ($invisible_fields as $field): ?> {
                    title: "<?= $field->title ?>",
                    data: "<?= $field->id ?>",
                    defaultContent: "<span class='tag is-warning'><?= lang('Admin.n/a') ?></span>", // If data not found, show n/a instead
                    visible: false, // Do not display this column
                    searchable: false, // Optional: remove from search if not needed
                    orderable: false, // Optional: disable sorting on this column
                    orderSequence: ["asc", "desc"],
                },
            <?php endforeach; ?>
            <?php foreach ($fields as $field): ?> {
                    title: "<?= $field->label ?>",
                    data: "<?= $field->id ?>",
                    defaultContent: "<span class='tag is-warning'><?= lang('Admin.n/a') ?></span>", // If data not found, show n/a instead
                    orderSequence: ["asc", "desc"],
                    render: function(data, type, row, meta) {
                        const fieldType = '<?= $field->type ?>';
                        const fieldClassName = <?= isset($field->className) ? "'" . $field->className . "'" : 'null' ?>;
                        if (type === 'display') {
                            if (data) {
                                let limit = 150; // Data limit to display show more button

                                if (data.length > limit) {
                                    return he.encode(data.substring(0, limit - 3) + '...') +
                                        ` <a class="is-link" data-content="${escape(data)}" onclick="openContentModal(this)"><?= lang('Admin.seeMore') ?></a>`;
                                } else {
                                    return data;
                                }

                            } else if (data === '') {
                                return "<span class='tag'><?= lang('Admin.(empty)') ?></span>";
                            }
                        }
                        return data;
                    }
                },
            <?php endforeach; ?>
        ],

        columnDefs: [{
            type: 'date',
            targets: <?= $date_field_ids ?>
        }],

        // Layout
        layout: {
            topStart: {
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
                                    // AJAX request to restore entries (POSTing the ids array)
                                    $.ajax({
                                        url: '<?= base_url('admin/entry-data/clear-history/' . $entry['id']) ?>', // Adjust this URL as needed.
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
            // Add double-click event
            $(row).on('dblclick', function() {
                // Get the ID from the data
                var id = data.id;

                // Reserved
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

    /* End of configs */

    /* Init */

    var hyperTable = new DataTable('#hyperTable', options);

    /* End of init */
</script>
<?= $this->endSection() ?>