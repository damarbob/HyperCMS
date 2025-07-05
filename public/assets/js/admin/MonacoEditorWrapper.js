import * as monaco from "https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/+esm";
import prettier from "https://cdn.jsdelivr.net/npm/prettier@3.3.3/+esm";
import * as parserHtml from "https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/html.mjs";
import parserCss from "https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/postcss.mjs"; // CSS parser
import parserBabel from "https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/babel.mjs"; // For JS formatting
import parserEstree from "https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/estree.mjs"; // ES Tree parser

// Define custom themes once globally.
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

/**
 * A reusable wrapper for creating and managing a Monaco Editor instance.
 *
 * Options:
 *   - editorContainerSelector: ID of the HTML element where the editor should be created.
 *   - textareaSelector: (Optional) ID of a hidden <textarea> to sync the editor content.
 *   - onSave: (Optional) callback when user saves the editor content (Ctrl + S).
 *   - language: The language mode (default: 'json').
 *   - autoLayout: Boolean for automatic layout (default: true).
 */
export default class MonacoEditorWrapper {
  constructor({
    editorContainerSelector = "#monaco",
    textareaSelector = "#monaco-textarea",
    onSave = (editor) => {
      // Default save action: just log the content.
      console.log("Save action triggered. Current content:", editor.getValue());
    },
    language = "javascript",
    autoLayout = true,
  } = {}) {
    this.editorContainer = document.querySelector(editorContainerSelector);
    if (!this.editorContainer) {
      throw new Error(
        `Editor container with id "${editorContainerSelector}" not found.`
      );
    }

    console.log(monaco.languages.getLanguages());

    // Optional elements
    this.textareaInput = document.querySelector(textareaSelector);
    this.onSave = onSave;

    // Create model instance with the specified language.
    this.model = monaco.editor.createModel("", language);

    // Create the Monaco Editor instance.
    this.editor = monaco.editor.create(this.editorContainer, {
      // language: language,
      model: this.model,
      // language: language,
      theme: window.hyper_isDarkMode ? "vs-dark-dsm" : "vs-dsm",
      automaticLayout: autoLayout,
    });

    // Initialize value and bind event
    this.#initializeValue();
    this.#bindModelChange();

    // Register common commands.
    this.#registerCommands();
  }

  getMonaco() {
    return monaco;
  }

  // Initialize the editor value from the hidden textarea if it exists.
  #initializeValue() {
    if (this.textareaInput) {
      this.editor.getModel().setValue(this.textareaInput.value);
    }

    // When the textarea content changes, update the Monaco editor.
    this.textareaInput.addEventListener("change", () => {
      const textareaContent = this.textareaInput.value;
      // Only update if the content is different.
      if (this.editor.getValue() !== textareaContent) {
        this.editor.getModel().setValue(textareaContent);
      }
    });
  }

  // Update the hidden textarea with the current editor content.
  #updateTextarea() {
    if (this.textareaInput) {
      this.textareaInput.value = this.editor.getValue();
    }
  }

  // Bind change events on the editor model.
  #bindModelChange() {
    this.editor.onDidChangeModelContent(() => {
      this.#updateTextarea();
    });
  }

  // Register editor commands.
  #registerCommands() {
    // Toggle fullscreen (Ctrl/Cmd + Alt + Z)
    this.editor.addAction({
      id: "toggle-fullscreen",
      label: "Toggle Fullscreen",
      keybindings: [
        monaco.KeyMod.CtrlCmd | monaco.KeyMod.Alt | monaco.KeyCode.KeyZ,
      ],
      keybindingContext: "editorTextFocus",
      run: () => {
        if (!document.fullscreenElement) {
          this.editorContainer.requestFullscreen();
        } else {
          document.exitFullscreen();
        }
      },
    });

    // Format JSON using Prettier when available,
    this.editor.addAction({
      id: "format-code",
      label: "Format Code",
      keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyB],
      keybindingContext: "editorTextFocus",
      run: () => this.#formatCode(this.editor),
    });

    // Save the code (Ctrl/Cmd + S)
    this.editor.addAction({
      id: "save-content",
      label: "Save Content",
      keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
      keybindingContext: "editorTextFocus",
      run: () => this.onSave(this.editor),
    });
  }

  async #formatCode(editor) {
    const content = editor.getValue();
    const languageId = editor.getModel().getLanguageId();

    try {
      let formatted = content;

      // Determine parser and plugins based on language
      switch (languageId) {
        case "json":
          const parsed = JSON.parse(content);
          formatted = JSON.stringify(parsed, null, 2);
          break;
        case "html":
          // Apply Prettier formatting for HTML parts
          formatted = await prettier.format(content, {
            parser: "html",
            plugins: [parserHtml],
          });
          break;
        case "javascript":
        case "typescript":
          // Apply Prettier formatting for HTML parts
          formatted = await prettier.format(content, {
            parser: "babel",
            plugins: [parserBabel, parserEstree],
          });
          break;
        case "css":
          formatted = await prettier.format(content, {
            parser: "css",
            plugins: [parserCss],
          });
          break;
        case "scss":
          formatted = await prettier.format(content, {
            parser: "scss",
            plugins: [parserCss],
          });
          break;
        case "less":
          formatted = await prettier.format(content, {
            parser: "less",
            plugins: [parserCss],
          });
          break;
        default:
          // Fallback to JSON if content is valid JSON
          try {
            JSON.parse(content);
          } catch {
            throw new Error(`No formatter available for ${languageId}`);
          }
      }

      // Apply incremental edit to preserve undo history
      const fullRange = editor.getModel().getFullModelRange();
      editor.executeEdits("", [
        {
          range: fullRange,
          text: formatted,
          forceMoveMarkers: true,
        },
      ]);
    } catch (e) {
      if (!e.message.includes("No formatter available")) {
        alert(`Formatting error: ${e.message}`);
      }
      console.error("Formatting error:", e);
    }
  }
}
