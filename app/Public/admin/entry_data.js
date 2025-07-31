/* Configs */
var hyper = window.hyper;

var lang = hyper.config.locale;

// CSRF
var csrfName = hyper.config.csrfToken;
var csrfHash = hyper.config.csrfHash;

var options = {
  processing: true,
  serverSide: true,

  pageLength: hyper.data.pageLength,

  // Configure the AJAX endpoint and method.
  ajax: {
    url: `${hyper.config.baseUrl}/api/v1/entry-data`,
    type: "POST",
    data: function (d) {
      d.id = hyper.data.entry.id;
    },
  },

  // Define the columns based on your "models" table data.
  // Adjust the rendering if you wish to, for example, stringify JSON fields.
  columns: [
    // Invisible fields (not shown in the table)
    ...hyper.data.invisible_fields.map((field) => ({
      title: field.title,
      name: field.id, // Add name to allow reference from datatables
      data: field.id,
      defaultContent:
        "<span class='tag is-warning'>" + hyper.lang.Admin.na + "</span>",
      visible: false,
      searchable: false,
      orderable: false,
      orderSequence: ["asc", "desc"],
    })),
    // Visible fields
    ...hyper.data.fields.map((field) => ({
      title: field.label,
      name: field.id, // Add name to allow reference from datatables
      data: field.id,
      defaultContent:
        "<span class='tag is-warning'>" + hyper.lang.Admin["n/a"] + "</span>",
      orderSequence: ["asc", "desc"],
      render: function (data, type, row, meta) {
        // Use the field definition from the mapping
        const fieldType = field.type;
        const fieldClassName = field.className ? field.className : null;

        if (type === "display") {
          if (data) {
            let limit = 150; // Data limit to display show more button

            if (data.length > limit) {
              return (
                he.encode(data.substring(0, limit - 3) + "...") +
                `<a class="is-link ml-2" onclick='openPreviewModal("${escape(
                  data
                )}")'>${hyper.lang.Admin.seeMore}</a>`
              );
            } else {
              return data;
            }
          } else if (data === "") {
            return (
              "<span class='tag'>" + hyper.lang.Admin["(empty)"] + "</span>"
            );
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

  // Order descending by date_created
  order: {
    name: "date_created",
    dir: "desc",
  },

  // Layout
  layout: {
    topStart: {
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
                text: hyper.lang.Admin.thisActionPermanentlyDeleteAllHistorical,
              })
              .then((result) => {
                if (result.isConfirmed) {
                  // AJAX request to clear entry history
                  $.ajax({
                    url: `${hyper.config.baseUrl}admin/entry-data/clear-history/${hyper.data.entry.id}`, // Adjust this URL as needed.
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
    // Add double-click event
    $(row).on("dblclick", function () {
      // Get the ID from the data
      var id = data.id;

      // Reserved
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

/* End of init */
