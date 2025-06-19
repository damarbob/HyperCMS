/**
 * GrapesJS Plugin: grapesjs-hyper-dependencies
 *
 * This plugin injects Hyper CMS assets into the editor's canvas so that
 * the preview accurately reflects the final output. It adds CSS and JS
 * dependencies to the canvas configuration and adjusts the canvas styling.
 */
grapesjs.plugins.add(
  "grapesjs-hyper-dependencies",
  function (editor, opts = {}) {
    // Initialize canvas config properties if not present
    if (editor.config) {
      editor.config.canvas = editor.config.canvas || {};
      editor.config.canvas.styles = editor.config.canvas.styles || [];
      editor.config.canvas.scripts = editor.config.canvas.scripts || [];

      // CSS dependencies to load in the head of the canvas
      const cssHeadDependencies = [
        "https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css",
        "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css",
        `${window.hyper.config.baseUrl}assets/css/hyper-admin.css`,
      ];

      // Merge CSS dependencies without duplicating
      cssHeadDependencies.forEach((item) => {
        if (!editor.config.canvas.styles.includes(item)) {
          editor.config.canvas.styles.push(item);
        }
      });

      // JS dependencies to load in the head of the canvas;
      // Different URLs are used based on the environment setting.
      const jsHeadDependencies = [
        window.hyper.config.environment !== "production"
          ? "https://unpkg.com/@popperjs/core@2/dist/umd/popper.min.js"
          : "https://unpkg.com/@popperjs/core@2",
        window.hyper.config.environment !== "production"
          ? "https://unpkg.com/tippy.js@6/dist/tippy-bundle.umd.js"
          : "https://unpkg.com/tippy.js@6",
        `${window.hyper.config.baseUrl}assets/js/vendor/tinymce/tinymce.min.js`,
        "https://cdn.jsdelivr.net/npm/sweetalert2@11",
        "https://cdnjs.cloudflare.com/ajax/libs/Clamp.js/0.5.1/clamp.min.js",
        "https://cdn.jsdelivr.net/npm/flatpickr",
        `${window.hyper.config.baseUrl}assets/js/main.js`,
      ];

      // Merge JS dependencies without duplicates
      jsHeadDependencies.forEach((item) => {
        if (!editor.config.canvas.scripts.includes(item)) {
          editor.config.canvas.scripts.push(item);
        }
      });
    }

    // After the editor has loaded, perform additional adjustments on the canvas
    editor.on("load", function () {
      const canvasDoc = editor.Canvas.getDocument();

      // Adjust the canvas background color to match Bulma's scheme
      canvasDoc.body.style.backgroundColor = "var(--bulma-scheme-main)";

      // Find the wrapper element and add padding (as defined by Bulma sizing)
      const wrapper = canvasDoc.body.querySelector("[data-gjs-type=wrapper]");
      if (wrapper) {
        wrapper.style.padding = "var(--bulma-size-large)";
      }

      // Define an array of external scripts to inject into the canvas body.
      // These scripts are meant for preview only and flagged to exclude them
      // when exporting/saving the output.
      const scriptsToInject = [];

      // Inject each script into the canvas body and flag for no export
      scriptsToInject.forEach((scriptUrl) => {
        const scriptEl = canvasDoc.createElement("script");
        scriptEl.src = scriptUrl;
        scriptEl.setAttribute("data-no-export", "true");
        canvasDoc.body.appendChild(scriptEl);
        console.log(`Injected script: ${scriptUrl}`);
      });
    });
  }
);
