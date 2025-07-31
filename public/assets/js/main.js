import "./admin/translations/I18n.js"; // Initializes the translations.
import SwalWrapper from "./admin/SwalWrapper.js";
import MonacoEditorWrapper from "./admin/MonacoEditorWrapper.js";
import InputPopulator from "./admin/InputPopulator.js";
import InputCreator from "./admin/InputCreator.js";
import { encodeFormInputsToJson as EncodeFormInputsToJson } from "./admin/use-case/Form.js";
import { hexDecode, hexEncode } from "./admin/use-case/Hex.js";
import FileManager from "./file-manager/FileManager.js";
import { areUrisEqual } from "./admin/use-case/Url.js";
import { replacePlaceholders } from "./admin/use-case/Text.js";
import { getNavbarHeight } from "./admin/use-case/Dimens.js";

// ============================================================================
// Bootstrap Functions
// ============================================================================
// These functions wrap around the imported classes and utilities,
// returning new instances or invoking the necessary functionality.

function swalWrapper() {
  return new SwalWrapper();
}

function monacoEditorWrapper(options = {}) {
  return new MonacoEditorWrapper({ ...options });
}

function inputCreator(options = {}) {
  return new InputCreator({ ...options });
}

function inputPopulator(container) {
  return new InputPopulator(container);
}

function encodeFormInputsToJson(name, form, formData) {
  return EncodeFormInputsToJson(name, form, formData);
}

function fileManager() {
  return new FileManager();
}

function textReplacePlaceholders(str, replacements) {
  return replacePlaceholders(str, replacements);
}

function dimensGetNavbarHeight() {
  return getNavbarHeight();
}

// ============================================================================
// Exports for ES Module Users
// ============================================================================
// Export the wrapper functions so that they can be imported in an ES module based project.
export {
  swalWrapper,
  monacoEditorWrapper,
  inputCreator,
  inputPopulator,
  encodeFormInputsToJson,
  fileManager,
  textReplacePlaceholders,
};

// ============================================================================
// Global (Traditional Script) Exposure
// ============================================================================
// If the code is run in a browser (i.e. window is available),
// merge the wrapper functions and utilities into the global `window.hyper`
// object to support traditional script usage.
if (typeof window !== "undefined") {
  window.hyper = {
    ...window.hyper, // preserve existing properties, if any
    factory: {
      ...window.hyper?.factory,
      // Instantiate some modules (like Swal and FileManager) immediately,
      // while exposing functions for others (like monacoEditorWrapper)
      swal: swalWrapper(),
      monaco: monacoEditorWrapper,
      inputCreator,
      inputPopulator,
      fileManager: fileManager(),
    },
    util: {
      ...window.hyper?.util,
      uri: {
        ...window.hyper?.util?.uri,
        areUrisEqual,
      },
      form: {
        ...window.hyper?.util?.form,
        encodeFormInputsToJson,
      },
      text: {
        ...window.hyper?.util?.text,
        replacePlaceholders: textReplacePlaceholders,
      },
      hex: {
        ...window.hyper?.util?.hex,
        encode: hexEncode,
        decode: hexDecode,
      },
      dimens: {
        ...window.hyper?.util?.dimens,
        navbarHeight: dimensGetNavbarHeight(),
      },
    },
  };
}
