<?= $this->extend('admin/layout/page') ?>

<?= $this->section('content') ?>
<div class="block">
    <table id="usersTable" class="table is-fullwidth">
        <thead>
            <tr>
                <th>#</th>
                <th><?= lang('Auth.username') ?></th>
                <th><?= lang('Auth.email') ?></th>
                <th><?= lang('UserManagement.groups') ?></th>
            </tr>
        </thead>
    </table>
</div>

<!-- User Modal -->
<div class="modal" id="userModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title"><?= lang('UserManagement.userForm') ?></p>
            <button class="delete" onclick="closeModal('userModal')"></button>
        </header>

        <form id="userForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="userId">

            <div class="modal-card-body">

                <!-- Username -->
                <div class="field">
                    <label class="label"><?= lang('Auth.username') ?></label>
                    <div class="control">
                        <input class="input" type="text" name="username" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="field">
                    <label class="label"><?= lang('Auth.email') ?></label>
                    <div class="control">
                        <input class="input" type="email" name="email" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="field">
                    <label class="label"><?= lang('Auth.password') ?></label>
                    <div class="control">
                        <input class="input" type="password" name="password" id="password" value="">
                        <p class="help"><?= lang('UserManagement.leaveBlankToKeepCurrentPassword') ?></p>
                    </div>
                </div>

                <!-- Groups -->
                <div class="field">
                    <label class="label"><?= lang('UserManagement.groups') ?></label>
                    <div class="checkboxes">
                        <?php foreach ($groups as $group => $config): ?>
                            <label class="checkbox">
                                <input type="checkbox" name="groups[]" value="<?= $group ?>">
                                <?= ucfirst($group) ?>
                            </label>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <footer class="modal-card-foot">
                <div class="buttons">
                    <!-- Submit -->
                    <button type="submit" class="button is-primary"><?= lang('Admin.save') ?></button>
                    <!-- Cancel -->
                    <button type="button" class="button"><?= lang('Admin.cancel') ?></button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link href="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.css" rel="stylesheet" integrity="sha384-wAbr9qEp5JojSKDr01s3gfk2usG6WR/OfpUIFEliYPzIBy5Jr9WBChdyqfWfbtt6" crossorigin="anonymous">

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.js" integrity="sha384-JYvoIYf/4ra9ifw1ESGWSNm3QVSdAuT8OaSDJLTKTkRWntshpsM1beOZKdjAXOAb" crossorigin="anonymous"></script>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script>
    $(document).ready(function() {
        const lang = window.hyper.lang.Admin;

        const table = $('#usersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '<?= base_url('admin/users/get-users') ?>',
            columns: [{
                    data: 'id'
                },
                {
                    data: 'username'
                },
                {
                    data: 'email'
                },
                {
                    data: 'groups',
                    render: function(data, type, row, meta) {
                        if (type === 'display') {
                            let tags = "<div class='tags are-small'>";

                            // Split the comma-separated groups into an array and trim each one
                            let groupsArray = data.split(',').map(group => group.trim());

                            // Populate groups
                            groupsArray.forEach(group => {
                                tags += `<span class="tag">${group}</span>`;
                            });

                            tags += "</div>";

                            return tags;
                        }
                        return data;
                    }
                },
            ],
            layout: {
                topStart: {
                    buttons: [{
                            // New button
                            text: `<span class="icon"><i class="fa-solid fa-plus"></i></span><span>${lang.new}</span>`,
                            className: "is-primary",
                            action: function(e, dt, node, config) {
                                $('#userId').val('');
                                openModal($('#userModal')[0]);
                            }
                        },
                        {
                            // Edit button
                            extend: "selected",
                            text: '<i class="fa-solid fa-pen-to-square"></i>',
                            titleAttr: lang.delete,
                            className: "is-info",
                            action: function(e, dt, node, config) {
                                var selectedRows = dt
                                    .rows({
                                        selected: true,
                                    })
                                    .data()
                                    .toArray();
                                if (selectedRows.length > 0) {
                                    // Map the selected rows into an array of IDs
                                    var ids = selectedRows.map(function(row) {
                                        return row.id;
                                    });

                                    if (config.environment !== "production") {
                                        console.log("Delete IDs:", ids);
                                    }

                                    editUser(ids[0]);
                                } else {
                                    window.hyper.factory.swal.error(lang.selectToDelete);
                                }
                            },
                        },
                        {
                            extend: "colvis", // Column visibility button
                            text: '<i class="fa-solid fa-table"></i>',
                            titleAttr: lang.data,
                        },
                        {
                            extend: "excelHtml5", // Export to Excel using HTML5 features
                            text: '<i class="fa-solid fa-download"></i>',
                            titleAttr: lang.excel,
                        },
                        {
                            extend: "print", // Print button
                            text: '<i class="fa-solid fa-print"></i>',
                            titleAttr: lang.print,
                            exportOptions: {
                                orthogonal: false
                            }
                        },
                        {
                            // Refresh button
                            text: '<i class="fa-solid fa-arrows-rotate"></i>',
                            titleAttr: lang.refresh,
                            action: function(e, dt, node, config) {
                                dt.ajax.reload(function() {
                                    window.hyper.factory.swal.success(lang.successfullyRefreshed);
                                });
                            },
                        },
                        {
                            // Delete button
                            extend: "selected",
                            text: '<i class="fa-solid fa-trash"></i>',
                            titleAttr: lang.delete,
                            className: "is-danger hyper-delete",
                            action: function(e, dt, node, config) {
                                var selectedRows = dt
                                    .rows({
                                        selected: true,
                                    })
                                    .data()
                                    .toArray();
                                if (selectedRows.length > 0) {
                                    // Map the selected rows into an array of IDs
                                    var ids = selectedRows.map(function(row) {
                                        return row.id;
                                    });

                                    if (config.environment !== "production") {
                                        console.log("Delete IDs:", ids);
                                    }

                                    deleteUser(ids[0]);
                                } else {
                                    window.hyper.factory.swal.error(lang.selectToDelete);
                                }
                            },
                        },
                    ],
                },
                topEnd: {
                    pageLength: {
                        menu: [10, 25, 50, 100],
                    },
                    search: {
                        placeholder: lang.search,
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
                $(row).on("dblclick", function() {
                    editUser(data.id);
                });
            },

            // Additional DataTable plugins
            colReorder: true,
            fixedHeader: true,
            responsive: true,
            select: true,
        });

        $('#usersTable').on('click', 'button.is-edit', function(e) {
            const id = $(this).attr('data-id');
            editUser(id);
        });

        $('#usersTable').on('click', 'button.is-delete', function(e) {
            const id = $(this).attr('data-id');
            deleteUser(id);
        });

        $('#userForm').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            $.ajax({
                url: '<?= base_url('admin/users/save') ?>',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        window.hyper.factory.swal.success(response.message);
                    } else {
                        window.hyper.factory.swal.error(response.message);
                    }
                    table.ajax.reload();
                    closeModal(document.getElementById('userModal'));
                }
            });
        });
    });

    function editUser(id) {
        $.get('<?= base_url('admin/users/') ?>' + id, function(user) {
            console.log(user);
            $('#userId').val(user.id);
            $('[name="username"]').val(user.username);
            $('[name="email"]').val(user.email);

            // Split the comma-separated groups into an array and trim each one
            let groupsArray = user.groups.split(',').map(group => group.trim());

            // Populate groups
            groupsArray.forEach(group => {
                $(`[name="groups[]"][value="${group}"]`).prop('checked', true);
            });

            openModal(document.getElementById('userModal'));
        });
    }

    function deleteUser(id) {
        if (confirm('Are you sure?')) {
            $.ajax({
                url: `${window.hyper.config.baseUrl}admin/users/${id}/delete`,
                method: 'POST',
                data: {
                    [window.hyper.config.csrfToken]: window.hyper.config.csrfHash,
                }, // Include CSRF token for security
                success: function(response) {
                    if (response.success) {
                        window.hyper.factory.swal.success(response.message);
                    } else {
                        window.hyper.factory.swal.error(response.message);
                    }
                    $('#usersTable').DataTable().ajax.reload();
                }
            });
        }
    }
</script>
<?= $this->endSection() ?>