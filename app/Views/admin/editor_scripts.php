<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/grapes.min.js" integrity="sha256-/WoKyVG/rkPGHHHqcWUCUrZEAv4MNxbHL1wcN7Y5o30=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-flexbox@1.0.1/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.min.js"></script>
<script type="text/javascript">
  // These variables are output by PHP. They contain the merged JSON for components and CSS.
  const savedComponents = <?= json_encode($mapped_entry_fields->hyper_component_elements ?: []) ?>;
  const savedCss = <?= json_encode($mapped_entry_fields->hyper_css ?: '') ?>;

  // console.log(savedComponents);
  // console.log(savedCss);

  document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.hyper-editor').forEach((element) => {
      console.log("Initializing editor for:", element.id);
      element.style.display = 'none';
      const gjsId = `gjs-${element.id}`;
      element.insertAdjacentHTML('afterend', `<div id='${gjsId}'></div>`);
      initializeEditor(gjsId);
    });
  });

  function initializeEditor(id) {
    var editor = grapesjs.init({
      container: '#' + id,
      canvas: {
        styles: [
          // 'https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css',
        ],
      },
      storageManager: false,
      plugins: [
        testComponentPlugin,
        'grapesjs-bulma',
        'grapesjs-blocks-flexbox',
        'gjs-blocks-basic',
        'grapesjs-custom-code',
      ],
      pluginsOpts: {
        'gjs-blocks-basic': {
          // options
        },
        'grapesjs-custom-code': {
          // options
        },
      }
    });

    editor.on('load', function() {
      // Load page components and CSS
      if (typeof savedComponents !== 'undefined') {
        editor.setComponents(JSON.parse(savedComponents));
      }
      if (typeof savedCss !== 'undefined') {
        editor.setStyle(savedCss);
      }
    });

    // Add a save button with FontAwesome icon
    editor.Panels.addButton('options', {
      id: 'gjs-save-button',
      className: 'fa fa-save',
      command: 'gjs-save',
      attributes: {
        title: 'Save',
      },
    });

    // Custom command: Save only the overrides (page-specific data)
    editor.Commands.add('gjs-save', {
      run(editor, sender) {
        if (sender) sender.set('active', false);

        const htmlOutput = editor.getHtml();
        const cssOutput = editor.getCss();
        const components = editor.getComponents();
        const projectData = editor.getProjectData();

        console.log(components);

        // return;

        // Prepare payload
        const payload = [{
            id: 'hyper_html',
            value: htmlOutput
          },
          {
            id: 'hyper_css',
            value: cssOutput
          },
          {
            id: 'hyper_component_elements',
            value: JSON.stringify(components)
          },
          {
            id: 'hyper_page_project_data',
            value: JSON.stringify(projectData)
          },
          {
            id: 'hyper_page_template_id',
            value: '<?= isset($mapped_entry_fields->hyper_page_template_id) ? $mapped_entry_fields->hyper_page_template_id : '' ?>'
          }
        ];

        const newFormData = new FormData();
        newFormData.append('fields', JSON.stringify(payload));

        fetch('<?= base_url('api/test/entries/save/' . $entry->id) ?>', {
            method: 'POST',
            body: newFormData,
          })
          .then(response => response.json())
          .then((data) => {
            if (data.success) {
              Swal.fire("<?= lang('Admin.success') ?>", data.message, "success");
              if (data.redirect) {
                setTimeout(() => {
                  window.location.href = data.redirect;
                }, 1000);
              }
            } else {
              throw new Error(data.message);
              Swal.fire("<?= lang('Admin.error') ?>", data.message, "error");
            }
          })
          .then(data => {
            console.log('Save Successful:', data);
          })
          .catch(error => {
            console.error('Error saving data:', error);
          });
      }
    });
  }

  grapesjs.plugins.add('grapesjs-bulma', function(editor, opts = {}) {
    // 1. Optional: Inject Bulma CSS into the canvas if not already loaded.
    editor.on('load', function() {
      const canvasDoc = editor.Canvas.getDocument();
      // Check if Bulma is already linked. (You can add your own check here if needed.)
      if (!canvasDoc.querySelector('link[href*="bulma.min.css"]')) {
        const linkEl = canvasDoc.createElement('link');
        linkEl.setAttribute('rel', 'stylesheet');
        linkEl.setAttribute('href', 'https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css');
        canvasDoc.head.appendChild(linkEl);

        // Override default background color
        const styleEl = canvasDoc.createElement('style');
        styleEl.innerHTML = `body { background-color: var(--bulma-scheme-main) !important; }`;
        canvasDoc.head.appendChild(styleEl);
      }
    });

    // 2. Define a default SVG icon to use as the block media.
    const defaultMedia = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
      <!--! Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com -->
      <path d="M234.5 5.7c13.9-5 29.1-5 43.1 0l192 68.6C495 83.4 512 107.5 512 134.6l0 242.9c0 27-17 51.2-42.5 60.3l-192 68.6c-13.9 5-29.1 5-43.1 0l-192-68.6C17 428.6 0 404.5 0 377.4L0 134.6c0-27 17-51.2 42.5-60.3l192-68.6zM256 66L82.3 128 256 190l173.7-62L256 66zm32 368.6l160-57.1 0-188L288 246.6l0 188z"/>
    </svg>
  `;

    // 3. Register Bulma-based blocks.
    // Each block's text is wrapped with a <span> to ensure that text is editable.

    // Bulma Hero Section Block
    editor.Blocks.add('bulma-hero', {
      label: 'Hero',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <section class="hero is-primary">
        <div class="hero-body">
          <div class="container">
            <h1 class="title">
              <span>Your Title</span>
            </h1>
            <h2 class="subtitle">
              <span>Your Subtitle</span>
            </h2>
          </div>
        </div>
      </section>
    `
    });

    // Bulma Navbar Block
    editor.Blocks.add('bulma-navbar', {
      label: 'Navbar',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <nav class="navbar" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
          <a class="navbar-item" href="#">
            <img src="https://bulma.io/images/bulma-logo.png" alt="Bulma logo">
          </a>
          <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
          </a>
        </div>
        <div id="navbarMenu" class="navbar-menu">
          <div class="navbar-start">
            <a class="navbar-item">
              <span>Home</span>
            </a>
            <a class="navbar-item">
              <span>Documentation</span>
            </a>
          </div>
        </div>
      </nav>
    `
    });

    // Bulma Button Block
    editor.Blocks.add('bulma-button', {
      label: 'Button',
      category: 'Bulma',
      media: defaultMedia,
      content: `<button class="button is-primary"><span>Click Me</span></button>`
    });

    // Bulma Card Block
    editor.Blocks.add('bulma-card', {
      label: 'Card',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <div class="card">
        <div class="card-image">
          <figure class="image is-4by3">
            <img src="https://bulma.io/images/placeholders/1280x960.png" alt="Placeholder image">
          </figure>
        </div>
        <div class="card-content">
          <div class="media">
            <div class="media-left">
              <figure class="image is-48x48">
                <img src="https://bulma.io/images/placeholders/96x96.png" alt="Placeholder image">
              </figure>
            </div>
            <div class="media-content">
              <p class="title is-4"><span>John Doe</span></p>
              <p class="subtitle is-6"><span>@johndoe</span></p>
            </div>
          </div>
          <div class="content">
            <span>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</span>
          </div>
        </div>
      </div>
    `
    });

    // Bulma Notification Block
    editor.Blocks.add('bulma-notification', {
      label: 'Notification',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <div class="notification is-info">
          <button class="delete"></button>
          <span>This is a notification.</span>
      </div>
    `
    });

    // Bulma Columns Block
    editor.Blocks.add('bulma-columns', {
      label: 'Columns',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <div class="columns">
        <div class="column">
          <div class="box"><span>Column 1</span></div>
        </div>
        <div class="column">
          <div class="box"><span>Column 2</span></div>
        </div>
        <div class="column">
          <div class="box"><span>Column 3</span></div>
        </div>
      </div>
    `
    });

    // Bulma Container Block
    editor.Blocks.add('bulma-container', {
      label: 'Container',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <div class="container">
        <span>Add your content here</span>
      </div>
    `
    });

    // Bulma Section Block
    editor.Blocks.add('bulma-section', {
      label: 'Section',
      category: 'Bulma',
      media: defaultMedia,
      content: `
      <section class="section">
        <div class="container">
          <span>Your section content</span>
        </div>
      </section>
    `
    });
  });


  // Example plugin to load test components as blocks
  function testComponentPlugin(editor) {

    const defaultMedia = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M234.5 5.7c13.9-5 29.1-5 43.1 0l192 68.6C495 83.4 512 107.5 512 134.6l0 242.9c0 27-17 51.2-42.5 60.3l-192 68.6c-13.9 5-29.1 5-43.1 0l-192-68.6C17 428.6 0 404.5 0 377.4L0 134.6c0-27 17-51.2 42.5-60.3l192-68.6zM256 66L82.3 128 256 190l173.7-62L256 66zm32 368.6l160-57.1 0-188L288 246.6l0 188z"/></svg>';

    <?php foreach ($test_components as $component): ?>
      editor.Blocks.add('<?= url_title($component['component_title']) ?>', {
        label: '<?= $component['component_title'] ?>',
        media: defaultMedia,
        content: `<?= $component['component_content'] ?>`,
      });
    <?php endforeach; ?>
  }
</script>