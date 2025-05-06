import "./admin/translations/I18n.js";
import SwalWrapper from "./admin/SwalWrapper.js";
import MonacoEditorWrapper from "./admin/MonacoEditorWrapper.js";
import InputPopulator from "./admin/InputPopulator.js";
import InputCreator from "./admin/InputCreator.js";
import { encodeFormInputsToJson as EncodeFormInputsToJson } from "./admin/use-case/Form.js";
import { hexDecode, hexEncode } from "./admin/use-case/Hex.js";
import FileManager from "./file-manager/FileManager.js";
import { areUrisEqual } from "./admin/use-case/Url.js";

/* Bootstrap the app */

// Define the function
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

// For ES modules
export {
  swalWrapper,
  monacoEditorWrapper,
  inputCreator,
  inputPopulator,
  encodeFormInputsToJson,
  fileManager,
};

// For traditional script usage
if (typeof window !== "undefined") {
  window.hyper_swal = swalWrapper();
  window.hyper_monaco = monacoEditorWrapper;
  window.hyper_inputCreator = inputCreator;
  window.hyper_inputPopulator = inputPopulator;
  window.hyper_encodeFormInputsToJson = encodeFormInputsToJson;
  window.hyper_hexEncode = hexEncode;
  window.hyper_hexDecode = hexDecode;
  window.hyper_fileManager = fileManager();
  window.hyper_areUrisEqual = areUrisEqual;
}
