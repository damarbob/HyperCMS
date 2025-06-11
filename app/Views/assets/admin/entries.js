/* Configs */
var config = window.hyper.config; // Get the config object
var locale = config.locale; // Get locale from the hyper data
var lang = window.hyper.lang.Admin; // Get the language data for the 'Admin' section
var data = window.hyper.data; // Get the data object

// CSRF
var csrfName = config.csrfToken;
var csrfHash = config.csrfHash;

// States
var activeModelFilter = null; // Hold the filter model_id; null means no filter
var isTrash = true; // Set to true to show trashed entries.

// DataTables options
// Modify our DataTable options to supply additional data to the AJAX request:
var options = {
  processing: true,
  serverSide: true,

  pageLength: data.pageLength,

  ajax: {
    url: `${config.baseUrl}/api/v1/entries`,
    type: "POST",
    data: function (d) {
      // Whether trash view is enabled
      d.trash = isTrash;

      // Include the filter model_id if it is active.
      if (activeModelFilter) {
        d.model_id = activeModelFilter;
      }
    },
  },

  columns: [
    {
      title: lang.id,
      name: "id",
      data: "id",
      visible: false,
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.modelId,
      name: "model_id",
      data: "model_id",
      visible: false,
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.model,
      name: "model_name",
      data: "model_name",
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.fields,
      name: "fields",
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
      title: lang.createdBy,
      name: "created_by",
      data: "created_by",
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.editedBy,
      name: "edited_by",
      data: "edited_by",
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.createdAt,
      name: "created_at",
      data: "created_at",
      type: "date",
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.dateModified,
      name: "date_modified",
      data: "date_modified",
      type: "date",
      orderSequence: ["asc", "desc"],
    },
    {
      title: lang.deletedBy,
      name: "deleted_by",
      data: "deleted_by",
      visible: false,
      orderSequence: ["asc", "desc"],
    },
  ],

  order: {
    name: "date_modified",
    dir: "desc",
  },

  layout: {
    topStart: {
      buttons: [
        {
          // New button
          text: `<span class="icon"><i class="fa-solid fa-plus"></i></span><span>${lang.new}</span>`,
          className: "is-primary js-modal-trigger hyper-new",
          attr: {
            "data-target": "newModal",
          },
        },
        {
          // Empty trash button
          text: `<span class="icon"><i class="fa-solid fa-face-tired"></i></span><span>${lang.emptyTrash}</span>`,
          className: "is-danger hyper-purge-deleted",
          action: function (e, dt, node, config) {
            // Confirm empty trash
            window.hyper.factory.swal.confirm().then((result) => {
              if (result.isConfirmed) {
                emptyTrash();
              }
            });
          },
        },
        {
          // Filter button
          text: `<span class="icon"><i class="fa-solid fa-filter"></i></span><span>${lang.filter}</span>`,
          className: "is-info js-modal-trigger",
          attr: {
            "data-target": "filterModal",
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
          // Toggle trash view button
          text: `<span class="icon"><i class="fa-solid fa-recycle"></i></span><span>${lang.trash}</span>`,
          className: "is-warning",
          action: function (e, dt, node, config) {
            toggleTrashView(); // Call the function to toggle trash view

            if (isTrash) {
              $(node).addClass("is-active");
              $(node).html(
                `<span class="icon"><i class="fa-solid fa-xmark"></i></span><span>${lang.exit}</span>`
              );
            } else {
              $(node).removeClass("is-active");
              $(node).html(
                `<span class="icon"><i class="fa-solid fa-recycle"></i></span><span>${lang.trash}</span>`
              );
            }
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

              deleteEntries(ids);
            } else {
              window.hyper.factory.swal.error(lang.selectToDelete);
            }
          },
        },
        {
          // Restore button
          extend: "selected",
          text: `<span class="icon"><i class="fa-solid fa-rotate-left"></i></span><span>${lang.restore}</span>`,
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

              if (config.environment !== "production") {
                console.log("Restore IDs:", ids);
              }

              restoreEntries(ids);
            } else {
              window.hyper.factory.swal.error(lang.selectToRestore);
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
      let modelId = data.model_id;
      let id = data.id;

      window.location.href = window.hyper.util.text.replacePlaceholders(
        window.hyper.data.links.edit,
        {
          modelId: modelId,
          id: id,
        }
      );
    });
  },

  // Additional DataTable plugins
  colReorder: true,
  fixedHeader: true,
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
      languageUrl = "https://cdn.datatables.net/plug-ins/2.2.2/i18n/en-GB.json";
  }
  options.language = {
    url: languageUrl,
  };
}

/* End of configs */

/* Init */

// Initialize our DataTable (assuming our table has the ID "hyperTable")
var hyperTable = new DataTable("#hyperTable", options);

toggleTrashView(); // Initialize the trash view based on the default value

/* End of init */

/* Views */

// Modal & filter logic

// Handle filter button clicks within the filter modal:
$(document).on("click", ".filter-model-btn", function () {
  // Get the chosen model id
  activeModelFilter = $(this).data("model-id");

  // Toggle active state for UI feedback (using Bulma's "is-active" class)
  $(".filter-model-btn").removeClass("is-active");
  $(this).addClass("is-active");

  // Close the modal using our modal-close method
  closeModal(document.getElementById("filterModal"));
  // Reload the DataTable so the ajax call sends the new filter parameter.
  hyperTable.ajax.reload();
});

// Handle the "All" (reset) button:
$(document).on("click", ".filter-model-reset", function () {
  activeModelFilter = null;
  $(".filter-model-btn").removeClass("is-active");
  closeModal(document.getElementById("filterModal"));
  hyperTable.ajax.reload();
});

// Function to toggle the trash view
// and update the button visibility accordingly
function toggleTrashView() {
  isTrash = !isTrash; // Toggle the trash view
  hyperTable.ajax.reload(); // Reload the table data

  if (isTrash) {
    $("button.hyper-new").hide(250);
    $("button.hyper-delete").hide(250);
    $("button.hyper-purge-deleted").show(250);
    $("button.hyper-restore").show(250);
  } else {
    $("button.hyper-new").show(250);
    $("button.hyper-delete").show(250);
    $("button.hyper-purge-deleted").hide(250);
    $("button.hyper-restore").hide(250);
  }
}

function renderFieldTags(data) {
  // Parse the data if it's a string
  if (typeof data === "string") {
    try {
      data = JSON.parse(data);
    } catch (err) {
      return `<span class="tag is-light">${lang.invalidJson}</span>`;
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
        const fieldValue = field.value || "";

        // Helper: encode and optionally truncate text
        const formatText = (text, limit) =>
          he.encode(
            text.length > limit ? text.substring(0, limit - 3) + "..." : text
          );

        // Build tooltip content
        const tooltipContent = `
                        <div class="content" style="text-align: left; max-width: 300px;">
                        <p><strong>${fieldLabel}</strong></p>
                        ${
                          fieldValue
                            ? `<p style="overflow: hidden">${formatText(
                                String(fieldValue),
                                150
                              )}</p>`
                            : ""
                        }
                        </div>
                    `;

        // Build tag content
        const tagContent = fieldValue
          ? `<p style="overflow: hidden">${formatText(
              String(fieldValue),
              20
            )}</p>`
          : `(${fieldLabel})`;

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

function emptyTrash() {
  $.ajax({
    url: data.links.purge,
    type: "POST",
    data: {
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

// AJAX request to delete entries (POSTing the ids array)
function deleteEntries(ids) {
  $.ajax({
    url: data.links.delete,
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
          confirmButtonText: lang.undo,
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
    url: data.links.restore,
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
