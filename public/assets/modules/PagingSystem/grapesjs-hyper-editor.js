/**
 * GrapesJS Plugin: grapesjs-hyper-editor
 *
 * This plugin alters the appearance of the editor and adds Hyper CMS–compatible saving
 * functionality. It customizes styling options, adds UI components, and defines a custom
 * “save” command that cleans up the generated HTML, prepares data payloads, and sends
 * a POST request to persist page-specific overrides.
 *
 * @param {Object} editor - The GrapesJS editor instance.
 * @param {Object} opts - Plugin options containing field names for saving.
 * @param {string} [opts.htmlField="hyper_html"] - Field identifier for HTML content.
 * @param {string} [opts.cssField="hyper_css"] - Field identifier for CSS content.
 * @param {string} [opts.componentElementsField="hyper_component_elements"] - Field id for components.
 * @param {string} [opts.projectDataField="hyper_page_project_data"] - Field id for project data.
 */
grapesjs.plugins.add(
  "grapesjs-hyper-editor",
  function (
    editor,
    {
      htmlField = "hyper_html",
      cssField = "hyper_css",
      componentElementsField = "hyper_component_elements",
      projectDataField = "hyper_page_project_data",
    }
  ) {
    const lang = window.hyper.lang.Admin;

    editor.Keymaps.add('gjs-save', '⌘+s, ctrl+s', 'gjs-save', {
      prevent: true,
    });

    // --- Extend StyleManager: Add custom properties for filter effects ---
    // editor.StyleManager.addProperty("extra", {
    //   extend: "filter",
    // });
    // editor.StyleManager.addProperty("extra", {
    //   extend: "filter",
    //   property: "backdrop-filter",
    // });

    // --- Editor Appearance Adjustments ---
    // On load, add a save button to the options panel.
    editor.on("load", function () {
      editor.Panels.addButton("options", {
        id: "gjs-save-button",
        className: "fa fa-save",
        command: "gjs-save",
        attributes: {
          title: lang.save,
        },
      });

    });

    // --- Save Command Definition ---
    // This command cleans up the HTML output (removing non-exportable elements)
    // and extracts other override data (CSS, components, project data). It then builds
    // a payload matching the expected structure, includes CSRF tokens, and sends it via fetch.
    editor.Commands.add("gjs-save", {
      run(editor, sender) {
        const swal = window.hyper.factory.swal;
        // Show loader
        swal.loader({
          title: window.hyper.lang.PagingSystem.saving,
          text: window.hyper.lang.PagingSystem.pleaseWait,
        })

        if (sender && sender instanceof HTMLElement && sender.hasAttribute("active")) sender.set("active", false);

        // Gather outputs from the editor.
        let htmlOutput = editor.getHtml();
        const cssOutput = editor.getCss();
        const components = editor.getComponents();
        const project = editor.getProjectData();

        /* --- HTML Cleanup --- */
        // Use DOMParser to create a Document and remove elements marked with data-no-export.
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlOutput, "text/html");

        // Remove nodes not meant for export.
        doc.querySelectorAll("[data-no-export]").forEach((el) => {
          el.parentNode.removeChild(el);
        });
        // Serialize cleaned HTML back.
        htmlOutput = doc.documentElement.outerHTML;

        // if (window.hyper.config.environment !== "production") {
        //   console.log(htmlOutput);
        // }
        /* --- End of HTML Cleanup --- */

        // Prepare data to be sent: set the override fields with the processed content.
        const newEntryFields = window.hyper.data.mapped_entry_fields;
        newEntryFields[htmlField] = htmlOutput;
        newEntryFields[cssField] = cssOutput;
        newEntryFields[componentElementsField] = JSON.stringify(components);
        newEntryFields[projectDataField] = JSON.stringify(project);

        // Format payload: create an array of objects with {id, value} entries.
        const payload = Object.entries(newEntryFields).map(([key, value]) => ({
          id: key,
          value: value,
        }));

        // Prepare FormData with the payload and CSRF data.
        const newFormData = new FormData();
        newFormData.append("fields", JSON.stringify(payload));
        newFormData.append(
          window.hyper.config.csrfToken,
          window.hyper.config.csrfHash
        );

        // Send the payload via fetch to the endpoint for saving the entry.
        fetch(
          window.hyper.config.baseUrl +
          "admin/entries/" +
          window.hyper.data.entry.id,
          {
            method: "POST",
            headers: {
              Accept: "application/json",
            },
            body: newFormData,
          }
        )
          .then((response) => response.json())
          .then(function (data) {
            // Close swal loader
            swal.get().close();

            // On success, show a success alert and possibly redirect.
            if (data.success) {
              window.hyper.data.mapped_entry_fields.hyper_page_project_data = JSON.stringify(editor.getProjectData());
              swal.success(lang.success, {
                text: data.success,
              });
              if (data.redirect) {
                setTimeout(() => {
                  window.location.href = data.redirect;
                }, 1000);
              }
            } else {
              // On error, show an error alert.
              swal.error(lang.error, {
                text: data.error,
              });
              throw new Error(data.error);
            }
          })
          .catch(function (error) {
            // Close swal loader
            swal.get().close();

            console.error("Error saving data:", error);
            swal.error(lang.error, {
              text: error,
            });
          });
      },
    });
    // --- End Save Command ---
  }
);
