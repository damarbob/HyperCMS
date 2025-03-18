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
                                        <i class="fa-solid fa-box-open"></i>
                                    </span>
                                    <span class="model-name">
                                        <?= lang('Admin.newxEntry', ['x' => $model['name']]) ?>
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
                                <i class="fa-solid fa-box-open"></i>
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
<script src="//cdnjs.cloudflare.com/ajax/libs/list.js/1.5.0/list.min.js"></script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    var options = {
        valueNames: ['model-name']
    };

    var userList = new List('users', options);
</script>
<script type="text/javascript">
    var lang = '<?= $lang ?>';

    // Global variable to hold the filter model_id; null means no filter.
    var activeModelFilter = null;

    // Modify your DataTable options to supply additional data to the AJAX request:
    var options = {
        processing: true,
        serverSide: true,

        ajax: {
            url: "<?= base_url('/api/test/entries/dt') ?>",
            type: "POST",
            // Include the filter model_id if it is active.
            data: function(d) {
                if (activeModelFilter) {
                    d.model_id = activeModelFilter;
                }
            }
        },

        columns: [{
                title: "<?= lang("Admin.id") ?>",
                data: "id",
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
                render: function(data) {
                    if (typeof data === "string") {
                        try {
                            data = JSON.parse(data);
                        } catch (err) {
                            return data;
                        }
                    }
                    if (typeof data !== "object" || data === null) {
                        return data;
                    }
                    var tagClasses = ['is-primary', 'is-link', 'is-info', 'is-success'];
                    var output = "";
                    var i = 0;
                    var threshold = 30;
                    for (var key in data) {
                        if (data.hasOwnProperty(key)) {
                            var value = data[key];
                            if (typeof value === "object") {
                                value = JSON.stringify(value);
                            } else {
                                value = String(value);
                            }
                            var combined = key + ": " + value;
                            if (combined.length > threshold) {
                                combined = combined.substr(0, threshold - 3) + "...";
                            }
                            var tagClass = tagClasses[i % tagClasses.length];
                            output += '<span class="tag ' + tagClass +
                                '" style="margin-right: 5px; margin-bottom: 5px;">' +
                                combined + '</span> ';
                            i++;
                        }
                    }
                    return output;
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
                        className: 'is-primary js-modal-trigger',
                        text: '<i class="fa-solid fa-plus mr-2"></i><?= lang('Admin.new') ?>',
                        action: function(e, dt, node, config) {
                            openModal(document.getElementById('newModal'));
                        }
                    },
                    {
                        className: 'js-modal-trigger',
                        text: '<i class="fa-solid fa-filter mr-2"></i><?= lang('Admin.filter') ?>',
                        // Open the new filter modal instead of the new entry modal.
                        action: function(e, dt, node, config) {
                            openModal(document.getElementById('filterModal'));
                        }
                    },
                    "colvis",
                    {
                        extend: "excelHtml5",
                        title: "Models Export",
                    },
                    "print",
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
                window.location.href = "<?= $uri ?>" + id + "/edit?model_name=" + data.model_name;
            });
        },

        // Additional DataTable plugins
        colReorder: true,
        fixedHeader: true,
        responsive: true,
        select: true,
    };

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

    // Initialize your DataTable (assuming your table has the ID "hyperTable")
    var hyperTable = new DataTable('#hyperTable', options);


    // ---- MODAL & FILTER LOGIC ----

    // Handle filter button clicks within the filter modal:
    $(document).on('click', '.filter-model-btn', function() {
        // Get the chosen model id
        activeModelFilter = $(this).data('model-id');

        // Toggle active state for UI feedback (using Bulma's "is-active" class)
        $('.filter-model-btn').removeClass('is-active');
        $(this).addClass('is-active');

        // Close the modal using your modal-close method
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
</script>
<?= $this->endSection() ?>