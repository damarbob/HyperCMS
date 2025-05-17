/* Configs */
var hyper = window.hyper;

var lang = hyper["config"]["locale"]; // Get lang from the backend

// CSRF
var csrfName = hyper.config.csrfToken;
var csrfHash = hyper.config.csrfHash;

// States
var isTrash = true; // Set to true to show trashed models.

// DataTables options
var options = {
  processing: true,
  serverSide: true,

  // Page length options taken from settings.
  // This is the default value for the page length dropdown.
  pageLength: hyper.data.pageLength,

  // Configure the AJAX endpoint and method.
  ajax: {
    url: `${hyper.config.baseUrl}/api/v1/models`, // Our CI4 API endpoint that returns JSON
    type: "POST", // Often POST is used for server side processing
    data: function (d) {
      // Whether trash view is enabled
      d.trash = isTrash;
    },
  },

  // Define the columns based on our "models" table data.
  // Adjust the rendering if we wish to, for example, stringify JSON fields.
  columns: [
    {
      title: hyper.lang.Admin.id,
      data: "id",
      visible: false,
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.name,
      data: "name",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.fields,
      data: "fields",
      orderSequence: ["asc", "desc"],
      render: (data) => {
        return renderFieldTags(data);
      },
      createdCell: function (cell) {
        // Initialize tooltips for this cell
        $(cell)
          .find(".field-tag")
          .each(function () {
            tippy(this, {
              allowHTML: true,
              interactive: true,
              placement: "top-start",
            });
          });
      },
    },
    {
      title: hyper.lang.Admin.createdBy,
      data: "created_by",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.editedBy,
      data: "edited_by",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.createdAt,
      data: "created_at",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.dateModified,
      data: "date_modified",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.deletedBy,
      data: "deleted_by",
      visible: false,
      orderSequence: ["asc", "desc"],
    },
  ],

  columnDefs: [
    {
      type: "date",
      targets: [4, 5],
    },
  ],

  // Layout
  layout: {
    topStart: {
      buttons: {
        buttons: [
          {
            // New button
            text: `<span class="icon"><i class="fa-solid fa-plus"></i></span><span>${hyper.lang.Admin.new}</span>`,
            className: "is-primary hyper-new",
            action: function (e, dt, node, config) {
              window.location.href = `${hyper.config.baseUrl}admin/models/new`;
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

                window.hyper.factory.swal.confirm().then((result) => {
                  if (result.isConfirmed) {
                    deleteModels(ids);
                  }
                });
              } else {
                alert(hyper.lang.Admin.selectToDelete);
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

                restoreModels(ids);
              } else {
                alert(hyper.lang.Admin.selectToRestore);
              }
            },
          },
        ],
      },
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
      window.location.href = `${hyper.config.baseUrl}admin/models/${id}/edit`;
    });
  },

  // Enable additional DataTables plugins
  colReorder: true, // Allow column reordering
  fixedHeader: true, // Keep header fixed as we scroll
  responsive: true, // Make the table responsive on various devices
  select: true, // Allow row selection
};

// Order descending by date_modified (second last column). Assuming last column is always 'date_modified' column.
// @IMPORTANT: Changing the last column will require changing the index below regardless of column visibility (probably).
options.order = [[options.columns.length - 2, "desc"]];

// DataTables language
// Add language option only when locale is not 'en'
if (lang !== "en") {
  var languageUrl;

  // Determine which language file to use (example for Indonesian)
  switch (lang) {
    case "id":
      languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/id.json";
      break;
    // We can add cases here for other locales if needed
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

// Render colorful tags for the model fields
function renderFieldTags(data) {
  // Parse the data if it's a string
  if (typeof data === "string") {
    try {
      data = JSON.parse(data);
    } catch (err) {
      return `<span class="tag is-light">${hyper.lang.Admin.invalidJson}</span>`;
    }
  }

  // Return if data is not an object/array
  if (typeof data !== "object" || data === null) {
    return data;
  }

  // Define color classes for tags
  const tagClasses = [
    "is-primary",
    "is-link",
    "is-info",
    "is-success",
    "is-warning",
    "is-danger",
  ];
  let output = '<div class="tags are-small" style="margin-bottom: 0;">';
  let index = 0;

  // Process each field
  if (Array.isArray(data)) {
    data.forEach((field) => {
      if (field && typeof field === "object") {
        const tagClass = tagClasses[index % tagClasses.length];
        const fieldLabel = field.label || field.id || `Field ${index}`;
        const fieldType = field.type ? `Type: ${field.type}` : "";
        const fieldHelper = field.helper ? `Help: ${field.helper}` : "";

        // Create tooltip content
        const tooltipContent = `
                        <div class="content" style="text-align: left; max-width: 300px;">
                            <p><strong>${fieldLabel}</strong></p>
                            ${fieldType ? `<p>${fieldType}</p>` : ""}
                            ${fieldHelper ? `<p>${fieldHelper}</p>` : ""}
                            ${
                              field.className
                                ? `<p>Class: ${field.className}</p>`
                                : ""
                            }
                        </div>
                    `;

        // Create the tag with tooltip attributes
        output += `
                        <span class="tag ${tagClass} field-tag" 
                            data-tippy-content="${tooltipContent.replace(
                              /"/g,
                              "&quot;"
                            )}"
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
        const value =
          typeof data[key] === "object"
            ? JSON.stringify(data[key])
            : String(data[key]);

        output += `
                        <span class="tag ${tagClass}" 
                            style="margin-right: 5px; margin-bottom: 5px;">
                            ${key}: ${
          value.length > 30 ? value.substring(0, 27) + "..." : value
        }
                        </span>
                    `;
        index++;
      }
    }
  }

  output += "</div>";
  return output;
}

/* End of views */

/* Requests */

// AJAX request to delete models (POSTing the ids array)
function deleteModels(ids) {
  $.ajax({
    url: `${hyper.config.baseUrl}admin/models/delete`,
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
            restoreModels(ids);
          }
        });
      hyperTable.ajax.reload();
    },
    error: function (xhr, status, error) {
      // Handle errors
      window.hyper.factory.swal.error(error);
    },
  });
}

// AJAX request to restore models (POSTing the ids array)
function restoreModels(ids) {
  $.ajax({
    url: `${hyper.config.baseUrl}admin/models/restore`,
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
      // Handle errors
      window.hyper.factory.swal.error(error);
    },
  });
}

/* End of requests */
