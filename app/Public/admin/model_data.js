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
    url: `${hyper.config.baseUrl}/api/v1/model-data`, // Our CI4 API endpoint that returns JSON
    type: "POST", // Often POST is used for server side processing
    data: function (d) {
      d.id = hyper.data.model["id"];
    },
  },

  // Define the columns based on our "models" table data.
  // Adjust the rendering if we wish to, for example, stringify JSON fields.
  columns: [
    {
      title: hyper.lang.Admin.id,
      name: "data_id",
      data: "data_id",
      visible: false,
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.name,
      name: "name",
      data: "name",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.fields,
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
      title: hyper.lang.Admin.createdBy,
      name: "created_by",
      data: "created_by",
      orderSequence: ["asc", "desc"],
    },
    {
      title: hyper.lang.Admin.dateCreated,
      name: "date_created",
      data: "date_created",
      orderSequence: ["asc", "desc"],
    },
  ],

  columnDefs: [
    {
      type: "date",
      targets: [4],
    },
  ],

  order: {
    name: "date_created",
    dir: "desc",
  },

  // Layout
  layout: {
    topStart: {
      buttons: {
        buttons: [
          {
            // Use data button
            extend: "selected",
            text: `<span class="icon"><i class="fa-solid fa-check"></i></span><span>${hyper.lang.Admin.useData}</span>`,
            className: "is-primary is-in-iframe",
            action: function (e, dt, node, config) {
              window.hyper.factory.swal
                .confirm({
                  text: hyper.lang.Admin.thisActionWillOverwriteCurrentInput,
                })
                .then((result) => {
                  if (result.isConfirmed) {
                    confirmSelectedData();
                  }
                });
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
            // Clear history button
            text: '<i class="fa-solid fa-trash"></i>',
            className: "is-danger",
            titleAttr: hyper.lang.Admin.clearHistory,
            action: function (e, dt, node, config) {
              window.hyper.factory.swal
                .confirm({
                  text: hyper.lang.Admin
                    .thisActionPermanentlyDeleteAllHistorical,
                })
                .then((result) => {
                  if (result.isConfirmed) {
                    // AJAX request to clear model history
                    $.ajax({
                      url: `${hyper.config.baseUrl}admin/model-data/clear-history/${hyper.data.model["id"]}`, // Adjust this URL as needed.
                      type: "POST",
                      data: {
                        [csrfName]: csrfHash,
                      }, // Include CSRF token for security
                      dataType: "json", // Expecting JSON response from the server (if you modify your backend to return JSON)
                      success: function (response) {
                        window.hyper.factory.swal.success(response.success);
                        dt.ajax.reload();
                      },
                      error: function (xhr, status, error) {
                        // Handle errors here
                        window.hyper.factory.swal.error(error);
                      },
                    });
                  }
                });
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
    // Add double-click event to open preview modal
    $(row).on("dblclick", function () {
      // Get the ID from the data
      var id = data.id;

      // Open preview modal
      openPreviewModal(data.fields);
    });
  },

  initComplete: function (settings, json) {
    toggleTrashView(); // Initialize the trash view based on the default value
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

/* Additional functions */

function confirmSelectedData() {
  var selectedRows = hyperTable
    .rows({
      selected: true,
    })
    .data()
    .toArray();
  if (selectedRows.length > 0) {
    // Post the message with the deserialized data included
    window.parent.postMessage(
      {
        action: "modelDataSelected",
        data: selectedRows,
      },
      hyper.config.baseUrl
    );
  }
}

function openPreviewModal(content) {
  // Set the content in the modal's content area
  const contentArea = document.getElementById("contentArea");
  if (contentArea) {
    contentArea.innerHTML = he.encode(unescape(content));
  }
  // Get the modal element (you can also pass this in if desired)
  const modal = document.getElementById("contentModal");
  // Now open the modal using your openModal function (make sure it's defined)
  openModal(modal);
}

/* End of additional functions */
