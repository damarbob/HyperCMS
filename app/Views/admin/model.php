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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="text/javascript">
    var lang = '<?= $lang ?>';
    var options = {
        processing: true,
        serverSide: true,

        // Configure the AJAX endpoint and method.
        ajax: {
            url: "<?= base_url('/api/test/model/dt') ?>", // Your CI4 API endpoint that returns JSON, @TODO: remove test
            type: "POST", // Often POST is used for server side processing
            data: function(d) {
                d.id = <?= $id ?>;
            }
        },

        // Define the columns based on your "models" table data.
        // Adjust the rendering if you wish to, for example, stringify JSON fields.
        columns: [
            <?php foreach ($invisible_fields as $field): ?> {
                    title: "<?= $field->title ?>",
                    data: "<?= $field->id ?>",
                    visible: false, // Do not display this column
                    searchable: false, // Optional: remove from search if not needed
                    orderable: false, // Optional: disable sorting on this column
                    orderSequence: ["asc", "desc"],
                },
            <?php endforeach; ?>
            <?php foreach ($fields as $field): ?> {
                    title: "<?= $field->nama ?>",
                    data: "<?= $field->id ?>",
                    orderSequence: ["asc", "desc"],
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
                        className: 'is-primary',
                        text: '<i class="fa-solid fa-plus mr-2"></i>New',
                        action: function(e, dt, node, config) {
                            window.location.href = '<?= base_url("admin/entries/new?model_id=$id") ?>';
                        }
                    },
                    "colvis", // Column visibility button
                    {
                        extend: "excelHtml5", // Export to Excel using HTML5 features
                        title: "Models Export",
                    },
                    "print", // Print button
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
            // Add double-click event to navigate to Edit page
            $(row).on('dblclick', function() {
                // Get the ID from the data
                var id = data.id;

                // Navigate to the Edit page
                window.location.href = "<?= base_url('admin/entries/') ?>" + id + "/edit?model_name=" + data.model_name;
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