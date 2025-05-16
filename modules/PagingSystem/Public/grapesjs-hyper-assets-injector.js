/**
 * GrapesJS Plugin: grapesjs-hyper-assets-injector
 *
 * This plugin injects user-added assets (CSS and JS) into the editor's canvas.
 * It can replace the hyper-dependencies plugin. Note that you need to create
 * an assets model in Hyper CMS and set it as the primary assets model in the settings.
 */
grapesjs.plugins.add(
  "grapesjs-hyper-assets-injector",
  function (editor, opts = {}) {
    // --- Inject Head Assets into the Canvas Config ---
    if (editor.config) {
      // Ensure the canvas config properties exist
      editor.config.canvas = editor.config.canvas;
      editor.config.canvas.styles = editor.config.canvas.styles;
      editor.config.canvas.scripts = editor.config.canvas.scripts;

      // Inject CSS dependencies specified in "window.hyper.data.styles.head"
      const cssHeadDependencies = window.hyper.data.styles.head;
      cssHeadDependencies.forEach((item) => {
        // Only add the style if it isn't already present
        if (!editor.config.canvas.styles.includes(item)) {
          editor.config.canvas.styles.push(item);
        }
      });

      // Inject JS dependencies specified in "window.hyper.data.scripts.head"
      const jsHeadDependencies = window.hyper.data.scripts.head;
      jsHeadDependencies.forEach((item) => {
        if (!editor.config.canvas.scripts.includes(item)) {
          editor.config.canvas.scripts.push(item);
        }
      });
    }

    // --- Inject Body Scripts on Editor Load ---
    editor.on("load", function () {
      // Retrieve the document from the editor's canvas for DOM manipulation
      const canvasDoc = editor.Canvas.getDocument();

      // Get an array of JS scripts that should be injected into the canvas (for preview)
      const scriptsToInject = window.hyper.data.scripts.body;

      // Loop through each script URL
      scriptsToInject.forEach((scriptUrl) => {
        // Create a new <script> element with the corresponding URL
        const scriptEl = canvasDoc.createElement("script");
        scriptEl.src = scriptUrl;
        // Mark the script for exclusion from saved output using a custom attribute
        scriptEl.setAttribute("data-no-export", "true");
        // Append the script to the end of the <body>
        canvasDoc.body.appendChild(scriptEl);

        // Log the injected script if the environment is not production
        if (window.hyper.config.environment !== "production") {
          console.log(`Injected script: ${scriptUrl}`);
        }
      });
    });
  }
);
