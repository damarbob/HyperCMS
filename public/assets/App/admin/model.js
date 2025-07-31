/* Configs */
var hyper = window.hyper;

var lang = hyper["config"]["locale"]; // Get lang from the backend

// CSRF
var csrfName = hyper.config.csrfToken;
var csrfHash = hyper.config.csrfHash;

// States
var isTrash = true; // Set to true to show trash entries.

var options = {
  processing: true,
  serverSide: true,

  pageLength: hyper.data.pageLength,

  // Configure the AJAX endpoint and method.
  ajax: {
    url: `${hyper.config.baseUrl}/api/v1/model`,
    type: "POST",
    data: function (d) {
      d.id = hyper.data.id;
      d.trash = isTrash;
    },
  },

  // Define the columns based on your "models" table data.
  // Adjust the rendering if you wish to, for example, stringify JSON fields.
  columns: [
    // Map over the invisible fields
    ...hyper.data.invisible_fields.map((field) => ({
      title: field.title,
      name: field.id,
      data: field.id,
      defaultContent: `<span class='tag is-warning'>${hyper.lang.Admin["n/a"]}</span>`,
      visible: false, // Do not display in the table
      searchable: false, // Remove from search if not needed
      orderable: false, // Disable sorting for this column
      orderSequence: ["asc", "desc"],
    })),
    // Map over the visible fields
    ...hyper.data.fields.map((field) => ({
      title: field.label,
      name: field.id,
      data: field.id,
      defaultContent: `<span class='tag is-warning'>${hyper.lang.Admin["n/a"]}</span>`,
      orderSequence: ["asc", "desc"],
      render: function (data, type, row, meta) {
        // Handle rendering when displaying data
        if (type === "display") {
          if (data) {
            // If the field type is "textarea" with a class that includes "hyper-code-field",
            // render a button to open a code modal instead of displaying raw content.
            if (
              field.type === "textarea" &&
              typeof field.className === "string" &&
              field.className.includes("hyper-code-field")
            ) {
              return `<button class="button is-small" data-code="${escape(
                data
              )}" onclick="openCodeModal(this)">
                                            <span class="icon"><i class="fa-solid fa-code"></i></span>
                                        </button>`;
            } else {
              return data;
            }
          } else if (data === "") {
            return `<span class='tag'>${hyper.lang.Admin["(empty)"]}</span>`;
          }
        }
        return data;
      },
    })),
  ],

  columnDefs: [
    {
      type: "date",
      targets: hyper.data.date_field_ids,
    },
  ],

  order: {
    name: "date_modified",
    dir: "desc",
  },

  // Layout
  layout: {
    topStart: {
      buttons: [
        {
          text: `<span class="icon"><i class="fa-solid fa-plus"></i></span><span>${hyper.lang.Admin.new}</span>`,
          className: "is-primary hyper-new",
          action: function (e, dt, node, config) {
            window.location.href = hyper.data.links["new"];
          },
        },
        {
          extend: "colvis", // Column visibility button
          text: '<i class="fa-solid fa-table"></i>',
          titleAttr: hyper.lang.Admin.data,
        },
        {
          extend: "excelHtml5", // Export to Excel using HTML5 features
          text: '<i class="fa-solid fa-download"></i>',
          titleAttr: hyper.lang.Admin.excel,
        },
        {
          extend: "print", // Print button
          text: '<i class="fa-solid fa-print"></i>',
          titleAttr: hyper.lang.Admin.print,
        },
        {
          // Refresh button
          text: '<i class="fa-solid fa-arrows-rotate"></i>',
          titleAttr: hyper.lang.Admin.refresh,
          action: function (e, dt, node, config) {
            dt.ajax.reload(function () {
              window.hyper.factory.swal.success(
                hyper.lang.Admin.successfullyRefreshed
              );
            });
          },
        },
        {
          // Toggle trash view button
          text: `<span class="icon"><i class="fa-solid fa-recycle"></i></span><span>${hyper.lang.Admin.trash}</span>`,
          className: "is-warning",
          action: function (e, dt, node, config) {
            toggleTrashView(); // Call the function to toggle trash view

            if (isTrash) {
              $(node).addClass("is-active");
              $(node).html(
                `<span class="icon"><i class="fa-solid fa-xmark"></i></span><span>${hyper.lang.Admin.exit}</span>`
              );
            } else {
              $(node).removeClass("is-active");
              $(node).html(
                `<span class="icon"><i class="fa-solid fa-recycle"></i></span><span>${hyper.lang.Admin.trash}</span>`
              );
            }
          },
        },
        {
          // Delete button
          extend: "selected",
          text: '<i class="fa-solid fa-trash"></i>',
          titleAttr: hyper.lang.Admin.delete,
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

              if (hyper.config.environment !== "production") {
                console.log("Delete IDs:", ids);
              }

              deleteEntries(ids);
            } else {
              window.hyper.factory.swal.error(hyper.lang.Admin.selectToDelete);
            }
          },
        },
        {
          // Restore button
          extend: "selected",
          text: `<span class="icon"><i class="fa-solid fa-rotate-left"></i></span><span>${hyper.lang.Admin.restore}</span>`,
          className: "is-success hyper-restore",
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

              if (hyper.config.environment !== "production") {
                console.log("Restore IDs:", ids);
              }

              restoreEntries(ids);
            } else {
              window.hyper.factory.swal.error(hyper.lang.Admin.selectToRestore);
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
        placeholder: hyper.lang.Admin.search,
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
    // Add double-click event to navigate to Edit page
    $(row).on("dblclick", function () {
      // Get the ID from the data
      var id = data.id;

      // Navigate to the Edit page
      window.location.href = window.hyper.util.text.replacePlaceholders(
        hyper.data.links["edit"],
        {
          id: id,
        }
      );
    });
  },

  // Enable additional DataTables plugins
  colReorder: true, // Allow column reordering
  // Keep header fixed as we scroll
  fixedHeader: {
    header: true,
    headerOffset: hyper.util.dimens.navbarHeight, // Use the navbar height
  },
  responsive: true, // Make the table responsive on various devices
  select: true, // Allow row selection
};

// Add language option only when locale is not 'en'
if (lang !== "en") {
  var languageUrl;

  // Determine which language file to use (example for Indonesian)
  switch (lang) {
    case "id":
      languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/id.json";
      break;
    // You can add cases here for other locales if needed
    default:
      // Fallback (or choose not to override default English)
      languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/en-GB.json";
  }

  // Add the language configuration into the options
  options.language = {
    url: languageUrl,
  };
}

/* End of configs */

/* Init */

var hyperTable = new DataTable("#hyperTable", options);

toggleTrashView(); // Initialize the trash view based on the default value

/* End of init */

/* Views */

// Function to toggle the trash view
// and update the button visibility accordingly
function toggleTrashView() {
  isTrash = !isTrash; // Toggle the trash view
  hyperTable.ajax.reload(); // Reload the table data

  if (isTrash) {
    $("button.hyper-new").hide(250);
    $("button.hyper-delete").hide(250);
    $("button.hyper-restore").show(250);
  } else {
    $("button.hyper-new").show(250);
    $("button.hyper-delete").show(250);
    $("button.hyper-restore").hide(250);
  }
}

/* End of views */

/* Requests */

// AJAX request to delete entries (POSTing the ids array)
function deleteEntries(ids) {
  $.ajax({
    url: hyper.data.links["delete"],
    type: "POST",
    data: {
      ids: ids,
      [csrfName]: csrfHash,
    }, // Include CSRF token for security
    dataType: "json", // Expecting JSON response from the server
    success: function (response) {
      window.hyper.factory.swal
        .success(response.success, {
          showConfirmButton: true,
          confirmButtonText: hyper.lang.Admin.undo,
        })
        .then((result) => {
          if (result.isConfirmed) {
            restoreEntries(ids);
          }
        });
      hyperTable.ajax.reload();
    },
    error: function (xhr, status, error) {
      // Handle errors here
      window.hyper.factory.swal.error(error);
    },
  });
}

// AJAX request to restore entries (POSTing the ids array)
function restoreEntries(ids) {
  $.ajax({
    url: hyper.data.links["restore"],
    type: "POST",
    data: {
      ids: ids,
      [csrfName]: csrfHash,
    }, // Include CSRF token for security
    dataType: "json", // Expecting JSON response from the server
    success: function (response) {
      window.hyper.factory.swal.success(response.success);
      hyperTable.ajax.reload();
    },
    error: function (xhr, status, error) {
      // Handle errors here
      window.hyper.factory.swal.error(error);
    },
  });
}

/* End of requests */
