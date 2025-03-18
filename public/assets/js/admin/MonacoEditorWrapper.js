import * as monaco from "https://cdn.jsdelivr.net/npm/monaco-editor@0.52/+esm";

// Define custom themes once globally.
const defineThemes = () => {
  monaco.editor.defineTheme("vs-dsm", {
    base: "vs",
    inherit: true,
    rules: [],
    colors: {
      "editor.background": "#ffffff",
    },
  });

  monaco.editor.defineTheme("vs-dark-dsm", {
    base: "vs-dark",
    inherit: true,
    rules: [],
    colors: {
      "editor.background": "#14161a",
    },
  });
};

// Call it immediately.
defineThemes();

/**
 * A reusable wrapper for creating and managing a Monaco Editor instance.
 *
 * Options:
 *   - editorContainerId: ID of the HTML element where the editor should be created.
 *   - fieldsId: (Optional) ID of a hidden <textarea> to sync the editor content.
 *   - formId: (Optional) ID of a form to submit on command.
 *   - language: The language mode (default: 'json').
 *   - autoLayout: Boolean for automatic layout (default: true).
 */
export default class MonacoEditorWrapper {
  constructor({
    editorContainerId = "monaco",
    fieldsId = "fields",
    formId = "formNewModel",
    language = "json",
    autoLayout = true,
  } = {}) {
    this.editorContainer = document.getElementById(editorContainerId);
    if (!this.editorContainer) {
      throw new Error(
        `Editor container with id "${editorContainerId}" not found.`
      );
    }

    // Optional elements
    this.fieldsInput = document.getElementById(fieldsId);
    this.form = document.getElementById(formId);

    // Create the Monaco Editor instance.
    this.editor = monaco.editor.create(this.editorContainer, {
      value: "",
      language,
      theme: window.isDarkMode ? "vs-dark-dsm" : "vs-dsm",
      automaticLayout: autoLayout,
    });

    // Bind events once DOM is ready.
    document.addEventListener("DOMContentLoaded", () => {
      this.initializeValue();
      this.bindModelChange();
    });

    // Register common commands.
    this.registerCommands();
  }

  // Initialize the editor value from the hidden textarea if it exists.
  initializeValue() {
    if (this.fieldsInput) {
      this.editor.getModel().setValue(this.fieldsInput.value);
    }
  }

  // Update the hidden textarea with the current editor content.
  updateTextarea() {
    if (this.fieldsInput) {
      this.fieldsInput.value = this.editor.getValue();
    }
  }

  // Bind change events on the editor model.
  bindModelChange() {
    this.editor.onDidChangeModelContent(() => {
      this.updateTextarea();
    });
  }

  // Register editor commands.
  registerCommands() {
    // Toggle fullscreen (Ctrl/Cmd + Alt + Z)
    this.editor.addCommand(
      monaco.KeyMod.CtrlCmd | monaco.KeyMod.Alt | monaco.KeyCode.KeyZ,
      () => {
        if (!document.fullscreenElement) {
          this.editorContainer.requestFullscreen();
        } else {
          document.exitFullscreen();
        }
      }
    );

    // Format JSON using Prettier when available,
    // or fallback to native JSON formatting (Ctrl/Cmd + B)
    this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyB, () => {
      const content = this.editor.getValue();
      try {
        // For Prettier integration, uncomment and import the JSON parser plugin:
        //
        // import prettier from 'https://cdn.jsdelivr.net/npm/prettier@3.3.3/+esm';
        // import parserJson from 'https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/json.mjs';
        // const formatted = prettier.format(content, {
        //   parser: "json",
        //   plugins: [parserJson],
        // });
        //
        // Below we're using native JSON formatting:
        const parsed = JSON.parse(content);
        const formatted = JSON.stringify(parsed, null, 2);
        this.editor.setValue(formatted);
      } catch (e) {
        alert(
          "Invalid JSON: Unable to format the code. Please fix any errors and try again."
        );
        console.error("Formatting error:", e);
      }
    });

    // Submit the form (Ctrl/Cmd + S)
    this.editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
      if (this.form) {
        this.form.submit();
      } else {
        console.warn("Form id is not assigned to the editor");
      }
    });
  }
}

// Export the class (if needed) or instantiate it directly.
// For example, to initialize the editor:
// const myEditor = new MonacoEditorWrapper({
//   editorContainerId: "monaco",
//   fieldsId: "fields",
//   formId: "formNewModel",
//   language: "json",
// });

// Now `myEditor` is a reusable instance. You can instantiate further if your page has more editors.
