<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.8/dist/grapes.min.js" integrity="sha256-n+3Ev4VhpTpZdsfDDNafUvZJAo1iLiZeFbHGgHqkVB0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.min.js"></script>
<script type="text/javascript" src="<?= module_assets_url('PagingSystem', 'grapesjs-hyper-editor.js') ?>"></script>
<script type="text/javascript" src="<?= module_assets_url('PagingSystem', 'grapesjs-hyper-dependencies.js') ?>"></script>
<script type="text/javascript" src="<?= module_assets_url('PagingSystem', 'grapesjs-hyper-assets-injector.js') ?>"></script>
<script type="text/javascript" src="<?= module_assets_url('PagingSystem', 'grapesjs-hyper-custom-editor.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'grapesjs-import-code.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'hyper-starter-plugin.js') ?>"></script>

<!-- Additional GrapesJS plugins -->
<script src="https://cdn.jsdelivr.net/npm/grapesjs-tui-image-editor@1.0.2/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-style-gradient@3.0.3/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-style-bg@2.0.2/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-style-filter@1.0.2/dist/index.min.js"></script>

<!-- Bootstrap 5 Blocks -->
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-blocks-bootstrap-5.min.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-grid-system.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-card.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-button.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-typography.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-accordion.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-carousel.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-forms.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-alert.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-badge.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-breadcrumb.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-dropdown.js') ?>"></script>
<script type="module" src="<?= module_assets_url('PagingSystem', 'blocks/bootstrap5/grapesjs-bootstrap-navbar.js') ?>"></script>

<?php
helper('hyper_hex');
$requester = hex_encode($uri);

/* Default editor configurations */

// Can be overridden in the override file
$editorPlugins = [
  'grapesjs-hyper-editor',
  /** 
   * The grapesjs-hyper-dependencies plugin loads dependencies similar to those used by Hyper CMS, 
   * which include Bulma. For now, we will use Bootstrap components instead.
   */
  // 'grapesjs-hyper-dependencies', 
  'grapesjs-hyper-assets-injector',
  'grapesjs-hyper-components',
  'grapesjs-hyper-custom-editor',
  'grapesjs-blocks-bootstrap-5',
  'grapesjs-bootstrap-grid-system',
  'grapesjs-bootstrap-typography',
  'grapesjs-bootstrap-button',
  'grapesjs-bootstrap-navbar',
  'grapesjs-bootstrap-card',
  'grapesjs-bootstrap-accordion',
  'grapesjs-bootstrap-carousel',
  'grapesjs-bootstrap-forms',
  'grapesjs-bootstrap-badge',
  'grapesjs-bootstrap-breadcrumb',
  'grapesjs-bootstrap-alert',
  'grapesjs-bootstrap-dropdown',
  'grapesjs-custom-code',
  'grapesjs-tui-image-editor',
  'grapesjs-style-bg',
  'grapesjs-style-gradient',
  'grapesjs-style-filter',
  'grapesjs-import-code',
  'hyper-starter-plugin',
];

// Plugin options
$editorPluginsOpts = [
  'grapesjs-hyper-editor' => [
    'htmlField' => "hyper_html",
    'cssField' => "hyper_css",
    'componentElementsField' => "hyper_component_elements",
    'projectDataField' => "hyper_page_project_data",
  ],
  'grapesjs-hyper-custom-editor' => [
    'requester' => $requester,
    'baseUrl' => base_url(),
  ],
  'grapesjs-blocks-bootstrap-5' => [
    'gridDevices' => false,
    // 'gridDevicesPanel' => true,
    'formPredefinedActions' => null,
    'optionsStringSeparator' => '::'
  ],
  'grapesjs-import-code' => [
    'modalImportTitle' => 'Import Code',
    'modalImportButton' => 'Import',
  ],
];

// Selector manager
$selectorManager = [
  "appendTo" => "#selector-manager",
  'componentFirst' => true,
];

// Include override file if available
$editorScriptsOverrideFile = __DIR__ . '/.hyper-dev/editor_scripts_override.php';

if (file_exists($editorScriptsOverrideFile)) {
  include $editorScriptsOverrideFile;
} else {
  log_message('error', "$editorScriptsOverrideFile does not exist.");
}
?>

<script type="text/javascript">
  window.hyper_editorPlugins = <?= json_encode($editorPlugins) ?>;
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
  let data = (Array.isArray(projectData) && !projectData.length) ? defaultProjectData : projectData;

  // Add js editor plugin options
  const editorPluginsOptions = <?= json_encode($editorPluginsOpts) ?>;
  editorPluginsOptions['grapesjs-import-code']['modalImportContent'] = function(editor) {
    return editor.getHtml() + '<style>' + editor.getCss() + '</style>';
  };

  document.addEventListener("DOMContentLoaded", function() {

    $(document).ready(function() {
      const container = $('#gjs-editor-container');

      container.append(
        // Editor Wrapper
        $('<div>', {
          class: 'editor-wrapper',
          append: $('<div>', {

            // Main Section
            class: 'main-section',
            append: [

              // Left Panel
              $('<div>', {
                class: 'left-panel',
                append:

                  // Left Panel Container
                  $('<div>', {
                    class: 'left-panel-container',
                    append: [

                      // Left Panel Buttons
                      $('<div>', {
                        class: 'left-panel-buttons',
                        append: [

                          // Buttons for Blocks and Layers
                          $('<button>', {
                            class: 'panel-button',
                            'data-target': 'blocks-manager',
                            title: `${window.hyper.lang.PagingSystem.blocks}`,
                            html: '<i class="fas fa-square-plus"></i>'
                          }),
                          $('<button>', {
                            class: 'panel-button',
                            'data-target': 'layers-manager',
                            title: `${window.hyper.lang.PagingSystem.layers}`,
                            html: '<i class="fas fa-layer-group"></i>'
                          })
                        ]
                      }), // end of left-panel-buttons

                      // Left Panel Content
                      $('<div>', {
                        class: 'left-panel-content',
                        style: 'display: none;',
                        append: [

                          // Left Panel Header
                          $('<h4>', {
                            class: 'left-panel-content-header',
                            id: 'leftPanelHeader'
                          }),

                          // Search Input
                          $('<div>', {
                            class: 'field px-2',
                            append: $('<div>', {
                              class: 'control',
                              append: $('<input>', {
                                type: 'text',
                                id: 'blocksSearchInput',
                                placeholder: `${window.hyper.lang.PagingSystem.search}`,
                                class: 'left-panel-search input',
                                style: 'display: none;'
                              })
                            })
                          }),

                          // Content Panes
                          $('<div>', {
                            id: 'blocks-manager',
                            style: 'display: none;',
                            class: 'left-panel-content-pane'
                          }),
                          $('<div>', {
                            id: 'layers-manager',
                            style: 'display: none;',
                            class: 'left-panel-content-pane'
                          })
                        ]
                      }) // end of left-panel-content
                    ]
                  }) // end of left-panel-container
              }), // end of left-panel

              // Canvas Container
              $('<div>', {
                class: 'canvas-container',
                append: [
                  $('<div>', {
                    class: 'top-panel',
                    append: [
                      $('<div>', {
                        class: 'top-panel-container px-2',
                        append: [
                          $('<div>', {
                            class: 'brand-container is-flex is-flex-direction-row is-align-items-center is-justify-content-center is-flex-wrap-wrap', // Added wrap class
                            append: [
                              $('<a>', {
                                class: 'title is-6 m-0 mr-2', // Added right margin for spacing
                                text: `${(window.hyper.config.appName).charAt(0)}`,
                                href: `${window.hyper.config.baseUrl}admin`,
                                target: '_blank'
                              }),
                              $('<div>', {
                                class: 'tags',
                                id: 'appVersion',
                                append: [
                                  $('<span>', {
                                    class: 'tag is-primary',
                                    text: `v${window.hyper.config.appVersion}`
                                  })
                                ]
                              }),
                            ]
                          }),
                          $('<div>', {
                            class: 'devices-panel'
                          }),
                          $('<div>', {
                            class: 'options-panel'
                          })
                        ]
                      })
                    ]
                  }),
                  $('<div>', {
                    id: 'gjs-editor'
                  }) // Editor container
                ]
              }),
              $('<div>', {
                class: 'right-panel',
                append: $('<div>', {
                  class: 'right-panel-container is-flex-direction-row',
                  append: [
                    $('<div>', {
                      class: 'right-panel-tabs tabs is-boxed is-centered',
                      append: [
                        $('<ul>', {
                          append: [
                            $('<li>', {
                              class: 'tab-button is-active',
                              'data-target': 'style-manager-container',
                              append: $('<a>', {
                                append: [
                                  $('<span>', {
                                    class: 'icon is-small',
                                    append: $('<i>', {
                                      class: 'fas fa-paint-brush'
                                    })
                                  }),
                                  $('<span>', {
                                    text: `${window.hyper.lang.PagingSystem.styles}`,
                                  }),
                                ]
                              })
                            }),
                            $('<li>', {
                              class: 'tab-button',
                              'data-target': 'trait-manager-container',
                              append: $('<a>', {
                                append: [
                                  $('<span>', {
                                    class: 'icon is-small',
                                    append: $('<i>', {
                                      class: 'fas fa-bars'
                                    })
                                  }),
                                  $('<span>', {
                                    text: `${window.hyper.lang.PagingSystem.properties}`,
                                  }),
                                ]
                              })
                            })
                          ]
                        })
                        // $('<div>', {
                        //   class: 'tab-button is-active',
                        //   'data-target': 'style-manager-container',
                        //   text: `${window.hyper.lang.PagingSystem.styles}`
                        // }),
                        // $('<div>', {
                        //   class: 'tab-button',
                        //   'data-target': 'trait-manager-container',
                        //   text: `${window.hyper.lang.PagingSystem.properties}`
                        // })
                      ]
                    }),
                    $('<div>', {
                      class: 'right-panel-content is-flex-grow-2',
                      append: [
                        $('<div>', {
                          id: 'style-manager-container',
                          class: 'right-panel-content-pane is-active',
                          append: [
                            $('<div>', {
                              id: 'no-select-state',
                              style: 'display: none;',
                              append: $('<div>', {
                                class: 'no-component',
                                append: [
                                  $('<i>', {
                                    class: 'fas fa-mouse-pointer'
                                  }),
                                  $('<h3>', {
                                    text: `${window.hyper.lang.PagingSystem.selectAComponentToEditStyles}`
                                  }),
                                ]
                              })
                            }),
                            $('<div>', {
                              id: 'selector-manager',
                              style: 'display: none;'
                            }),
                            $('<div>', {
                              id: 'selector-mode',
                              style: 'display: none;',
                              append: $('<div>', {
                                class: 'selector-mode-buttons buttons has-addons are-small px-1 mb-2',
                                append: [$('<button>', {
                                    class: 'selector-mode-button button is-primary is-selected',
                                    id: 'selectorModeComponent',
                                    title: `${window.hyper.lang.PagingSystem.component}`,
                                    append: $('<i>', {
                                      class: 'fas fa-cube'
                                    })
                                  }),
                                  $('<button>', {
                                    class: 'selector-mode-button button',
                                    id: 'selectorModeClass',
                                    title: `${window.hyper.lang.PagingSystem.class}`,
                                    append: $('<i>', {
                                      class: 'fas fa-tags'
                                    })
                                  })
                                ]
                              })
                            }),
                            $('<div>', {
                              id: 'style-manager',
                              style: 'display: none;'
                            })
                          ]
                        }),

                        // In your view file (CodeIgniter view)
                        $('<div>', {
                          id: 'trait-manager-container',
                          class: 'right-panel-content-pane',
                          append: [
                            $('<div>', {
                              id: 'trait-manager'
                            }),
                            $('<div>', {
                              class: 'trait-section',
                              append: $('<div>', {
                                class: 'attributes-panel',
                                append: [
                                  // No component state
                                  $('<div>', {
                                    id: 'no-trait-state',
                                    class: 'no-component',
                                    append: [
                                      $('<i>', {
                                        class: 'fas fa-mouse-pointer'
                                      }),
                                      $('<h3>', {
                                        text: `${window.hyper.lang.PagingSystem.selectAComponentToEditAttributes}`
                                      })
                                    ]
                                  }),

                                  // Attributes panel (initially hidden)
                                  $('<div>', {
                                    id: 'attributes-panel',
                                    style: 'display: none;',
                                    append: [
                                      $('<div>', {
                                        class: 'component-info',
                                        append: $('<div>', {
                                          class: 'component-type',
                                          append: [
                                            $('<i>', {
                                              class: 'fas fa-cube'
                                            }), ' ',
                                            $('<span>', {
                                              id: 'component-type-name'
                                            }), ' ',
                                            $('<span>', {
                                              id: 'component-id',
                                              class: 'has-text-weight-light'
                                            }),
                                          ],
                                        })
                                      }),
                                      $('<div>', {
                                        class: 'section-title',
                                        html: `<i class="fas fa-list"></i> ${window.hyper.lang.PagingSystem.customAttributes}`
                                      }),
                                      $('<div>', {
                                        class: 'custom-attributes is-flex is-flex-direction-column',
                                        append: [
                                          $('<div>', {
                                            class: 'attributes-list',
                                            id: 'attributesList'
                                          }),
                                          $('<button>', {
                                            class: 'add-attribute is-align-self-flex-end mt-2',
                                            id: 'addAttribute',
                                            title: `${window.hyper.lang.PagingSystem.addAttribute}`,
                                            html: '<i class="fas fa-plus"></i>'
                                          })
                                        ]
                                      }),
                                      $('<button>', {
                                        class: 'update-btn button is-primary is-fullwidth mt-3',
                                        id: 'updateAttributes',
                                        disabled: true,
                                        append: [
                                          $('<span>', {
                                            class: 'icon',
                                            append: $('<i>', {
                                              class: 'fas fa-sync-alt'
                                            })
                                          }),
                                          $('<span>', {
                                            text: `${window.hyper.lang.PagingSystem.updateAttribute}`
                                          })
                                        ],
                                      })
                                    ]
                                  })
                                ]
                              })
                            })
                          ]
                        })
                      ]
                    })
                  ]
                })
              })
            ]
          })
        })
      );

      // Initialize the editor
      initializeEditor('gjs-editor');
    });

  });

  function initializeEditor(id) {
    var editor = grapesjs.init({
      container: '#' + id,
      canvas: {
        // Placeholder to inject styles from backend
        styles: [],
        // Placeholder to inject scripts from backend
        scripts: [],
      },
      panels: {
        defaults: [],
      },
      height: 'calc(100vh - 41.6px)',
      storageManager: false,
      plugins: window.hyper_editorPlugins,
      // pluginsOpts: <?= json_encode($editorPluginsOpts); ?>,
      pluginsOpts: editorPluginsOptions,
      projectData: data,
      selectorManager: <?= json_encode($selectorManager); ?>,
      // selectorManager: {
      // appendTo: '#selector-manager'
      // },
      blockManager: {
        appendTo: '#blocks-manager'
      },
      layerManager: {
        appendTo: '#layers-manager'
      },
      styleManager: {
        appendTo: '#style-manager',
      },
      traitManager: {
        appendTo: '#trait-manager'
      }
    });

    editor.on('load', () => {
      $(window).on("beforeunload", (e) => {
        if (JSON.stringify(editor.getProjectData()) !== window.hyper.data.mapped_entry_fields.hyper_page_project_data) {
          // trigger the native confirmation dialog
          e.preventDefault();
          e.returnValue = "";
          // for some browsers, returning a value/string is still required
          return "";
        }
      });
    });

    if (window.hyper.config.environment !== 'production') {
      editor.on('update', () => {
        console.log('old: ', window.hyper.data.mapped_entry_fields.hyper_page_project_data);
        console.log('new: ', editor.getProjectData());
      });
    }

  }

  <?php if (ENVIRONMENT !== 'production'): ?>
    // Plugin to load hyper components as blocks if available
    grapesjs.plugins.add('grapesjs-hyper-components', function(editor, opts = {}) {

      const defaultMedia = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--> <path d = "M234.5 5.7c13.9-5 29.1-5 43.1 0l192 68.6C495 83.4 512 107.5 512 134.6l0 242.9c0 27-17 51.2-42.5 60.3l-192 68.6c-13.9 5-29.1 5-43.1 0l-192-68.6C17 428.6 0 404.5 0 377.4L0 134.6c0-27 17-51.2 42.5-60.3l192-68.6zM256 66L82.3 128 256 190l173.7-62L256 66zm32 368.6l160-57.1 0-188L288 246.6l0 188z" / ></svg>';

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