<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/grapes.min.js" integrity="sha256-/WoKyVG/rkPGHHHqcWUCUrZEAv4MNxbHL1wcN7Y5o30=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-flexbox@1.0.1/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.min.js"></script>
<script type="text/javascript">
  <?= serve_file('modules/PagingSystem/Public/grapesjs-hyper-editor.js')['body'] ?>
</script>
<script type="text/javascript">
  <?= serve_file('modules/PagingSystem/Public/grapesjs-hyper-dependencies.js')['body'] ?>
</script>
<script type="text/javascript">
  <?= serve_file('modules/PagingSystem/Public/grapesjs-hyper-assets-injector.js')['body'] ?>
</script>

<?php

/* Default editor configurations */

// Can be overridden in the override file
$editorPlugins = [
  'grapesjs-hyper-editor',
  'grapesjs-hyper-dependencies',
  'grapesjs-hyper-assets-injector',
  'grapesjs-hyper-components',
  'grapesjs-blocks-flexbox',
  'gjs-blocks-basic',
  'grapesjs-custom-code',
];

// Plugin options
$editorPluginsOpts = [
  'grapesjs-hyper-editor' => [
    'htmlField' => "hyper_html",
    'cssField' => "hyper_css",
    'componentElementsField' => "hyper_component_elements",
    'projectDataField' => "hyper_page_project_data",
  ]
];

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
  const project = {
    id: '<?= $entry['id'] ?>',
    // Use null if project data is not set!
    data: window.hyper.data.mapped_entry_fields.hyper_page_project_data ?? '[]'
  };
  const projectData = JSON.parse(project.data); // Parse project data

  // Default project data
  const defaultProjectData = {
    pages: [{
      component: `<h1 style="text-align:center">Hello world!</h1>`
    }]
  };

  // If project data is empty, use default project data
  const data = (Array.isArray(projectData) && !projectData.length) ? defaultProjectData : projectData;

  document.addEventListener("DOMContentLoaded", function() {

    // Create editor instance for each .hyper-editor element
    document.querySelectorAll('.hyper-editor').forEach((element) => {
      if (window.hyper.config.environment !== 'production') {
        console.log("Initializing editor for:", element.id);
      }

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
      projectData: data,
      selectorManager: <?= json_encode($selectorManager); ?>
    });

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