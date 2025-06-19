import jquery from "https://cdn.jsdelivr.net/npm/jquery@3.7.1/+esm";
import { config } from "../../../../js/Config.js";

/**
 * Hyper Starter Plugin for GrapesJS
 *
 * This plugin provides a starter component that connects to an API to fetch models and their fields,
 * allowing users to select a model and display its data in the editor.
 */
grapesjs.plugins.add("hyper-starter-plugin", function (editor, opts = {}) {
  // Arrays to store retrieved data
  let models = []; // Stores all model objects from the API
  let modelsForProp = []; // Stores models in {value, name} format for dropdowns
  let fields = []; // Stores fields for all models

  /**
   * Fetches models data from the API and initializes the plugin
   */
  function fetchModelsData() {
    jquery.ajax({
      url: `${config.baseUrl}api/test/models/dt`,
      type: "POST",
      dataType: "json",
      data: {},
      success: (response) => {
        if (response.data) {
          processModelsData(response.data);
          refreshSelectedComponent();
        }
      },
      error: function (xhr, status, error) {
        handleFetchError(error);
      },
    });
  }

  /**
   * Processes the models data from API response
   * @param {Array} data - The models data from API
   */
  function processModelsData(data) {
    data.forEach((item) => {
      models.push(item);
      modelsForProp.push({
        value: item.id,
        name: item.name,
      });
      fields.push(item.fields);
    });
  }

  /**
   * Refreshes the currently selected component if it's our starter plugin
   */
  function refreshSelectedComponent() {
    const component = editor.getSelected();
    if (component && component.is("starter-plugin")) {
      // Trigger a refresh by deselecting and reselecting
      editor.selectToggle(component);
      editor.selectToggle(component);
    }
  }

  /**
   * Handles errors during model data fetching
   * @param {string} error - The error message
   */
  function handleFetchError(error) {
    console.error("Error loading models:", error);
    // Show user-friendly notification
    editor.Modal.open({
      title: "Loading Error",
      content: "Failed to load models data. Please try again later.",
    });
  }

  /**
   * Gets fields for a specific model formatted for dropdown options
   * @param {string} modelId - The ID of the model
   * @returns {Array} Array of field options in {value, name} format
   */
  function getModelFieldsForProp(modelId) {
    const modelFieldsForProp = [];
    const selectedModel = models.find((model) => model.id === modelId);

    if (selectedModel && selectedModel.fields) {
      try {
        JSON.parse(selectedModel.fields).forEach((field) => {
          modelFieldsForProp.push({
            value: field.id,
            name: `${field.label} [${field.id}]`,
          });
        });
      } catch (e) {
        console.error("Error parsing model fields:", e);
      }
    }

    return modelFieldsForProp;
  }

  // Initial data fetch
  fetchModelsData();

  // Event handler when a component is selected
  editor.on("component:selected", (component) => {
    if (component.is("starter-plugin")) {
      const modelId = component.getTrait("modelProp").getValue();
      const modelFieldsForProp = getModelFieldsForProp(modelId);

      // Update component traits
      component.getTrait("modelProp").set("options", [...modelsForProp]);
      component.getTrait("titleProp").set("options", modelFieldsForProp);
    }
  });

  // Event handler when a trait value changes
  editor.on("trait:value", ({ trait, component, value }) => {
    if (trait.getName() === "modelProp" && component.is("starter-plugin")) {
      const modelFieldsForProp = getModelFieldsForProp(value);
      component.getTrait("titleProp").set("options", modelFieldsForProp);
    }
  });

  /**
   * Component script that runs when the component is rendered
   * @param {Object} props - Component properties
   */
  const script = function (props) {
    const { baseUrl, modelProp, titleProp } = props;

    const API_URL = `${baseUrl}api/test/model/dt`;
    const MODEL_ID = modelProp;

    // Show loading state
    $(`#hyper-starter-plugin-content`).html(`
      <div class="text-center my-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading data...</p>
      </div>
    `);

    // Abort any previous request
    if (window._queryItemAjax) {
      window._queryItemAjax.abort();
    }

    // Fetch items data
    window._queryItemAjax = $.ajax({
      url: API_URL,
      type: "POST",
      dataType: "json",
      data: {
        draw: 1,
        start: 0,
        id: MODEL_ID,
      },
      success: function (response) {
        renderItems(response.data);
      },
      error: function (xhr, status, error) {
        console.error("Error loading item:", error);
        // Show error message
        $(`#hyper-starter-plugin-content`).html(`
          <div class="alert alert-danger">
            Failed to load data. Please try again later.
            Error: ${error}.
          </div>
        `);
      },
    });

    /**
     * Renders items to the component
     * @param {Array} items - Items to render
     */
    function renderItems(items) {
      let html = "";

      if (items && items.length > 0) {
        items.forEach((item) => {
          html += `
            <div class="item">
              <h2 class="display-2 fw-bold">${item[titleProp] || "N/A"}</h2>
            </div>
          `;
        });
      } else {
        html = `<div class="alert alert-info">No items found</div>`;
      }

      $(`#hyper-starter-plugin-content`).html(html);
    }
  };

  // Define our component type
  editor.Components.addType("starter-plugin", {
    model: {
      defaults: {
        script,
        baseUrl: config.baseUrl,
        modelProp: modelsForProp.length > 0 ? modelsForProp[0].value : "", // Default to first model if available
        titleProp: "title",
        traits: [
          {
            type: "select",
            label: "Model",
            name: "modelProp",
            changeProp: true,
            options:
              modelsForProp.length > 0
                ? modelsForProp
                : [{ value: "", name: "Loading models..." }],
          },
          {
            type: "select",
            label: "Title field",
            name: "titleProp",
            changeProp: true,
            options: [{ value: "", name: "Select a model first" }],
          },
        ],
        "script-props": ["baseUrl", "modelProp", "titleProp"],
        styles: `
          .item {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #eee;
            border-radius: 0.25rem;
          }
        `,
        components: `
          <section class="section">
            <div id="hyper-starter-plugin-content" class="container vstack gap-4">
              <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Initializing component...</p>
              </div>
            </div>
          </section>
        `,
      },
    },
  });

  // Add the component to the blocks panel
  editor.Blocks.add("starter-plugin", {
    label: "Starter Plugin",
    category: "Hyper",
    media: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M0 96C0 60.7 28.7 32 64 32H384c35.3 0 64 28.7 64 64V416c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V96z"/></svg>`,
    content: { type: "starter-plugin" },
    attributes: {
      title: "Starter Plugin Block",
    },
  });
});
