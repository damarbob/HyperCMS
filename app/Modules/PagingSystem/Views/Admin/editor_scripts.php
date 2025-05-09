<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/grapes.min.js" integrity="sha256-/WoKyVG/rkPGHHHqcWUCUrZEAv4MNxbHL1wcN7Y5o30=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-flexbox@1.0.1/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.min.js"></script>

<?php

/* Default values */

// Can be overridden in the override file
$editorPlugins = [
  'grapesjs-bulma',
  'grapesjs-hyper-components',
  'grapesjs-blocks-flexbox',
  'gjs-blocks-basic',
  'grapesjs-custom-code',
];

// Plugin options
$editorPluginsOpts = [];

// Selector manager
$selectorManager = [];

// Include override file if available
$editorScriptsOverrideFile = __DIR__ . '/.hyper-dev/editor_scripts_override.php';

if (file_exists($editorScriptsOverrideFile)) {
  include $editorScriptsOverrideFile;
} else {
  log_message('error', "$editorScriptsOverrideFile does not exist.");
}
?>

<script type="text/javascript">
  // These variables are output by PHP. They contain the merged JSON for components and CSS.
  const entryFields = <?= json_encode($mapped_entry_fields) ?>;
  const projectData = {
    id: '<?= $entry->id ?>',
    // Use empty array if project data is not set!
    data: <?= json_encode(!empty($mapped_entry_fields['hyper_page_project_data']) ? ($mapped_entry_fields['hyper_page_project_data']) : "[]") ?>
  };
  const savedComponents = <?= json_encode(!empty($mapped_entry_fields['hyper_component_elements']) ? $mapped_entry_fields['hyper_component_elements'] : "[]") ?>;
  const savedCss = <?= json_encode($mapped_entry_fields['hyper_css'] ?: '') ?>;

  document.addEventListener("DOMContentLoaded", function() {

    // Create editor instance for each .hyper-editor element
    document.querySelectorAll('.hyper-editor').forEach((element) => {
      <?php if (ENVIRONMENT !== 'production'): ?>
        console.log("Initializing editor for:", element.id);
      <?php endif ?>

      element.style.display = 'none';
      const gjsId = `gjs-${element.id}`;
      element.insertAdjacentHTML('afterend', `<div id='${gjsId}'></div>`);
      initializeEditor(gjsId);
    });

  });

  // Initialize the Page Editor
  function initializeEditor(id) {
    var editor = grapesjs.init({
      container: '#' + id,
      canvas: {
        // Placeholder to inject styles from backend
        styles: [],
        // Placeholder to inject scripts from backend
        scripts: [],
      },
      panels: {},
      height: '100vh',
      storageManager: false,
      plugins: <?= json_encode($editorPlugins); ?>,
      pluginsOpts: <?= json_encode($editorPluginsOpts); ?>,
      projectData: JSON.parse(projectData.data) || {
        pages: [{
          component: `
            <div class="test">Initial content</div>
            <style>.test { color: red }</style>
          `
        }]
      },
      selectorManager: <?= json_encode($selectorManager); ?>
    });

    // Adds a new filter built-in style property which can be used 
    // for CSS properties like filter and backdrop-filter.
    editor.StyleManager.addProperty('extra', {
      extend: 'filter'
    });
    editor.StyleManager.addProperty('extra', {
      extend: 'filter',
      property: 'backdrop-filter'
    });

    editor.on('load', function() {
      // Add a save button with FontAwesome icon
      editor.Panels.addButton('options', {
        id: 'gjs-save-button',
        className: 'fa fa-save',
        command: 'gjs-save',
        attributes: {
          title: '<?= lang('Admin.save') ?>',
        },
      });
    });

    /* Save command */

    // Custom command: Save only the overrides (page-specific data)
    editor.Commands.add('gjs-save', {
      run(editor, sender) {
        if (sender) sender.set('active', false);

        let htmlOutput = editor.getHtml();
        const cssOutput = editor.getCss();
        const components = editor.getComponents();
        const projectData = editor.getProjectData();

        /* HTML cleanup */
        // Parse the HTML into a DOM Document using DOMParser
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlOutput, 'text/html');

        // Select all elements with the attribute data-no-export
        doc.querySelectorAll('[data-no-export]').forEach(el => {
          el.parentNode.removeChild(el);
        });

        // Serialize the cleaned DOM back into an HTML string for saving/exporting
        htmlOutput = doc.documentElement.outerHTML;
        <?php if (ENVIRONMENT !== 'production'): ?>
          console.log(htmlOutput);
        <?php endif ?>
        /* End of HTML cleanup */

        // Replace neccessary data
        const newEntryFields = <?= json_encode($mapped_entry_fields) ?>;
        newEntryFields['hyper_html'] = htmlOutput;
        newEntryFields['hyper_css'] = cssOutput;
        newEntryFields['hyper_component_elements'] = JSON.stringify(components);
        newEntryFields['hyper_page_project_data'] = JSON.stringify(projectData);

        // Prepare payload
        // Format payload to match desired entry data fields structure ({id, value})
        const payload = Object.entries(newEntryFields).map(([key, value]) => ({
          id: key,
          value: value
        }));

        const newFormData = new FormData();
        newFormData.append('fields', JSON.stringify(payload));
        newFormData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        fetch('<?= base_url('admin/entries/' . $entry->id) ?>', {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
            },
            body: newFormData,
          })
          .then(response => response.json())
          .then((data) => {
            if (data.success) {
              window.hyper_swal.success("<?= lang('Admin.success') ?>", {
                text: data.success
              });
              if (data.redirect) {
                setTimeout(() => {
                  window.location.href = data.redirect;
                }, 1000);
              }
            } else {
              window.hyper_swal.error("<?= lang('Admin.error') ?>", {
                text: data.error
              });
              throw new Error(data.error);
            }
          })
          .catch(error => {
            console.error('Error saving data:', error);
            window.hyper_swal.error("<?= lang('Admin.error') ?>", {
              text: error
            });
          });
      }
    });

    /* End of save command */

    /* Inject assets */

    // Add to canvas config (if editor not yet initialized)
    if (editor.config) {
      editor.config.canvas = editor.config.canvas || {};
      editor.config.canvas.styles = editor.config.canvas.styles || [];
      editor.config.canvas.scripts = editor.config.canvas.scripts || [];

      // CSS dependencies to load on head
      const cssHeadDependencies = <?= json_encode($styles['head'] ?? [], JSON_UNESCAPED_SLASHES) ?>;

      cssHeadDependencies.forEach((item) => {
        if (!editor.config.canvas.styles.includes(item)) {
          editor.config.canvas.styles.push(item);
        }
      });

      // JS dependencies to load on head
      const jsHeadDependencies = <?= json_encode($scripts['head'] ?? [], JSON_UNESCAPED_SLASHES) ?>;

      jsHeadDependencies.forEach((item) => {
        if (!editor.config.canvas.scripts.includes(item)) {
          editor.config.canvas.scripts.push(item);
        }
      });
    }

    editor.on("load", function() {
      const canvasDoc = editor.Canvas.getDocument();

      // Array of external scripts to inject into the canvas.
      // These scripts will be used in the editor for preview, but can be exclude
      // later when exporting/saving by filtering out nodes with data-no-export.
      const scriptsToInject = <?= json_encode($scripts['body'] ?? [], JSON_UNESCAPED_SLASHES) ?>;

      // Loop through each script URL and inject it at the end of the body.
      scriptsToInject.forEach((scriptUrl) => {
        const scriptEl = canvasDoc.createElement("script");
        scriptEl.src = scriptUrl;
        // Add a custom attribute to flag this script for exclusion from saved output.
        scriptEl.setAttribute("data-no-export", "true");
        canvasDoc.body.appendChild(scriptEl);
        
        <?php if (ENVIRONMENT !== 'production'): ?>
          console.log(`Injected script: ${scriptUrl}`);
        <?php endif ?>
      });
    });

    /* End of inject assets */

  }

  <?php if (ENVIRONMENT !== 'production'): ?>
    // Plugin to load hyper components as blocks if available
    grapesjs.plugins.add('grapesjs-hyper-components', function(editor, opts = {}) {

      const defaultMedia = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M234.5 5.7c13.9-5 29.1-5 43.1 0l192 68.6C495 83.4 512 107.5 512 134.6l0 242.9c0 27-17 51.2-42.5 60.3l-192 68.6c-13.9 5-29.1 5-43.1 0l-192-68.6C17 428.6 0 404.5 0 377.4L0 134.6c0-27 17-51.2 42.5-60.3l192-68.6zM256 66L82.3 128 256 190l173.7-62L256 66zm32 368.6l160-57.1 0-188L288 246.6l0 188z"/></svg>';

      <?php foreach ($test_components as $component): ?>
        editor.Blocks.add('<?= url_title($component['component_title']) ?>', {
          label: '<?= $component['component_title'] ?>',
          media: defaultMedia,
          content: `<?= $component['component_content'] ?>`,
        });
      <?php endforeach; ?>
    });
  <?php endif ?>
</script>