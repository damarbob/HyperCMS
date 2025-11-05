$(function () {
  /* Configs */
  var config = window.hyper.config; // Get the config object
  var locale = config.locale; // Get locale from the hyper data
  var lang = window.hyper.lang.Admin; // Get the language data for the 'Admin' section
  var data = window.hyper.data; // Get the data object
  var util = window.hyper.util; // Get the util object

  var options = {
    processing: true,
    serverSide: true,
    ajax: `${config.baseUrl}admin/users/get-users`,
    columns: [
      {
        data: "id",
      },
      {
        data: "username",
      },
      {
        data: "email",
      },
      {
        data: "groups",
        render: function (data, type, row, meta) {
          if (type === "display") {
            let tags = "<div class='tags are-small'>";

            // Split the comma-separated groups into an array and trim each one
            let groupsArray = data.split(",").map((group) => group.trim());

            // Populate groups
            groupsArray.forEach((group) => {
              tags += `<span class="tag">${group}</span>`;
            });

            tags += "</div>";

            return tags;
          }
          return data;
        },
      },
    ],
    layout: {
      topStart: {
        buttons: [
          {
            // New button
            text: `<span class="icon"><i class="fa-solid fa-plus"></i></span><span>${lang.new}</span>`,
            className: "is-primary",
            action: function (e, dt, node, config) {
              newUser();
            },
          },
          {
            // Edit button
            extend: "selected",
            text: '<i class="fa-solid fa-pen-to-square"></i>',
            titleAttr: lang.delete,
            className: "is-info",
            action: function (e, dt, node, config) {
              var selectedRows = dt
                .rows({
                  selected: true,
                })
                .data()
                .toArray();
              if (selectedRows.length > 0) {
                // Map the selected rows into an array of IDs
                var ids = selectedRows.map(function (row) {
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
              orthogonal: false,
            },
          },
          {
            // Refresh button
            text: '<i class="fa-solid fa-arrows-rotate"></i>',
            titleAttr: lang.refresh,
            action: function (e, dt, node, config) {
              dt.ajax.reload(function () {
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
            action: function (e, dt, node, config) {
              var selectedRows = dt
                .rows({
                  selected: true,
                })
                .data()
                .toArray();
              if (selectedRows.length > 0) {
                // Map the selected rows into an array of IDs
                var ids = selectedRows.map(function (row) {
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
    rowCallback: function (row, data, index) {
      $(row).on("dblclick", function () {
        editUser(data.id);
      });
    },

    // Additional DataTable plugins
    colReorder: true,
    // Keep header fixed as we scroll
    fixedHeader: {
      header: true,
      headerOffset: util.dimens.navbarHeight, // Use the navbar height
    },
    responsive: true,
    select: true,
  };

  // DataTables language
  if (locale !== "en") {
    var languageUrl;
    switch (locale) {
      case "id":
        languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/id.json";
        break;
      default:
        languageUrl =
          "https://cdn.datatables.net/plug-ins/2.2.2/i18n/en-GB.json";
    }
    options.language = {
      url: languageUrl,
    };
  }

  const table = $("#usersTable").DataTable(options);

  $("#usersTable").on("click", "button.is-edit", function (e) {
    const id = $(this).attr("data-id");
    editUser(id);
  });

  $("#usersTable").on("click", "button.is-delete", function (e) {
    const id = $(this).attr("data-id");
    deleteUser(id);
  });

  $("#userForm").submit(function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    $.ajax({
      url: `${config.baseUrl}admin/users/save`,
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          window.hyper.factory.swal.success(response.message);
        } else {
          window.hyper.factory.swal.error(response.message);
        }
        table.ajax.reload();
        closeModal(document.getElementById("userModal"));
      },
    });
  });

  function newUser() {
    // Clear inputs
    $("#userId").val("");
    $('[name="username"]').val("");
    $('[name="email"]').val("");
    $('[name="password"]').val("");

    // Reset groups checkbox selection
    $(`[name="groups[]"][value="user"]`).prop("checked", true); // Check the default role
    $('[name="groups[]"]').not('[value="user"]').prop("checked", false); // Uncheck the rest

    // Open user modal
    openModal($("#userModal")[0]);
  }

  function editUser(id) {
    // Clear inputs
    $('[name="password"]').val("");

    $.get(`${config.baseUrl}admin/users/${id}`, function (user) {
      console.log(user);
      $("#userId").val(user.id);
      $('[name="username"]').val(user.username);
      $('[name="email"]').val(user.email);

      // Split the comma-separated groups into an array and trim each one
      let groupsArray = user.groups.split(",").map((group) => group.trim());

      // Reset all group checkboxes
      $('[name="groups[]"]').prop("checked", false);

      // Populate groups
      groupsArray.forEach((group) => {
        $(`[name="groups[]"][value="${group}"]`).prop("checked", true);
      });

      openModal(document.getElementById("userModal"));
    });
  }

  function deleteUser(id) {
    if (confirm(window.hyper.lang.Admin.areYouSure)) {
      $.ajax({
        url: `${window.hyper.config.baseUrl}admin/users/${id}/delete`,
        method: "POST",
        data: {
          [window.hyper.config.csrfToken]: window.hyper.config.csrfHash,
        }, // Include CSRF token for security
        success: function (response) {
          if (response.success) {
            window.hyper.factory.swal.success(response.message);
          } else {
            window.hyper.factory.swal.error(response.message);
          }
          $("#usersTable").DataTable().ajax.reload();
        },
      });
    }
  }
});
