// InputPopulator.js

import { InputPopulatorTemplates } from "./templates/InputPopulatorTemplates.js";
import { getFilenameAndExtension } from "./use-case/Formatting.js";
import { bulmaInputPopulatorTemplates } from "./templates/addons/BulmaInputPopulatorTemplates.js";

/**
 * Class for populating form fields with meta data.
 *
 * @param {HTMLElement} container - The container for form fields.
 */
export default class InputPopulator {
  constructor(container) {
    this.container = container;
    InputPopulatorTemplates.setTemplate(bulmaInputPopulatorTemplates);
  }

  /**
   * Populates the form fields within the modal using meta data.
   *
   * @param {Array|Object} meta - An array of meta data objects (legacy) or an object with key-value pairs (new format).
   */
  populate(meta) {
    if (!meta) return;

    // Check if it's the legacy array format [{id, value}, ...]
    if (Array.isArray(meta)) {
      meta.forEach((item) => {
        const id = item.id;
        const element =
          this.container.querySelector(`#${id}`) ||
          this.container.querySelectorAll(`[name="${id}"]`);

        if (element) {
          this.populateElement(element, item);
        } else {
          console.error(`Element with ID or Name ${id} not found.`);
        }
      });
    }
    // New format: key-value pairs {key: value, ...}
    else if (typeof meta === "object") {
      Object.entries(meta).forEach(([id, value]) => {
        const element =
          this.container.querySelector(`#${id}`) ||
          this.container.querySelectorAll(`[name="${id}"]`);

        if (element) {
          this.populateElement(element, { id, value });
        } else {
          console.error(`Element with ID or Name ${id} not found.`);
        }
      });
    }
  }

  /**
   * Chooses the appropriate approach to populate the element based on its type.
   *
   * @param {Element | NodeList} element - The target element(s) to populate.
   * @param {Object} item - The meta data item.
   */
  populateElement(element, item) {
    const id = item.id;
    const value = item.value;

    if (element instanceof NodeList) {
      let finalElement =
        element.length > 0 ? element : document.getElementsByName(id + "[]");
      this.populateNodeList(finalElement, value);
    } else if (
      element instanceof HTMLInputElement ||
      element instanceof HTMLSelectElement ||
      element instanceof HTMLTextAreaElement
    ) {
      this.populateInputElement(element, id, value);
    } else {
      console.error("Unsupported element type for ID: " + id);
    }
  }

  /**
   * Populates a NodeList of input elements (such as checkboxes or radio buttons)
   * by setting the `checked` property based on a provided value or array of values.
   *
   * This function iterates over each element in the given NodeList. If a scalar value
   * is provided, it checks the element that has a matching value and marks it as checked.
   * If an array of values is provided, then each element whose value is included in the array
   * will have its `checked` property set to true.
   *
   * @param {NodeList} nodeList - The NodeList of input elements to update.
   * @param {*} value - The value or an array of values to match against.
   *
   * @example
   * // For a NodeList of radio buttons, check the radio button whose value equals 'yes':
   * populateNodeList(radioButtonNodeList, 'yes');
   *
   * @example
   * // For a NodeList of checkboxes, check all checkboxes that match any of the given values:
   * populateNodeList(checkboxNodeList, ['apple', 'orange']);
   */
  populateNodeList(nodeList, value) {
    nodeList.forEach((element) => {
      element.defaultChecked = false; // Reset default checked state
      element.checked = false; // Reset checked state

      // If value is not an array, check if the current element's value matches the value.
      if (!Array.isArray(value) && element.value === value) {
        element.checked = true;
      }
      // If value is an array, iterate over each of the provided values
      // and check if the element's value is included in the list.
      else if (Array.isArray(value)) {
        value.forEach((x) => {
          if (element.value === x) {
            element.checked = true;
          }
        });
      }
    });
  }

  /**
   * Populates an input, select, or textarea element with the provided value.
   *
   * @param {HTMLElement} element - The target element.
   * @param {string} id - The element's identifier.
   * @param {*} value - The value to set.
   */
  populateInputElement(element, id, value) {
    switch (element.type) {
      case "hidden":
      case "text":
      case "email":
      case "password":
      case "number":
      case "url":
      case "color":
      case "range":
      case "datetime-local":
        element.value = value;
        break;
      case "checkbox":
        element.checked = value === "on";
        break;
      case "file":
        this.handleFileInput(element, id, value);
        break;
      case "select-one":
        element.value = value;
        break;
      default:
        if (element.tagName === "TEXTAREA") {
          element.value = value;
        } else {
          element.innerHTML = value;
        }
        break;
    }
  }

  /**
   * Handles population of file input elements and displays previously uploaded file links.
   *
   * @param {HTMLInputElement} element - The file input element.
   * @param {string} id - The element's identifier.
   * @param {*} value - The file value(s); if not an array, converts to one.
   */
  handleFileInput(element, id, value) {
    if (!Array.isArray(value)) {
      if (!value) return; // Skip if there's no value
      value = [value]; // Convert to array if necessary
    }

    const filesInputOld = this.container.querySelector(`#${id}_old`);
    const filesFormHelper = this.container.querySelector(`#${id}_form-helper`);
    const filesInputParent = this.container.querySelector(`#${id}_parent`);

    if (filesInputOld && filesFormHelper && filesInputParent) {
      // Store the file links as JSON.
      filesInputOld.value = JSON.stringify(value);

      // Insert file link HTML from our template.
      value.forEach((fileUrl) => {
        // matches “http://…”, “https://…”, “//…”, or “www.…”
        const isAbsolute = /^(?:https?:\/\/|\/\/|www\.)/i.test(fileUrl);

        const url = isAbsolute
          ? fileUrl
          : window.hyper.config.baseUrl + fileUrl;

        filesFormHelper.append(
          InputPopulatorTemplates.fileLink({
            url,
            filename: fileUrl,
          })
        );
      });

      // Insert the delete button HTML.
      // filesInputParent.insertAdjacentHTML("beforeend", InputPopulatorTemplates.deleteFile({id}));
      filesInputParent.append(InputPopulatorTemplates.deleteFile({ id }));

      const buttonDeleteFile = this.container.querySelector(
        `#${id}_button-delete-file`
      );
      buttonDeleteFile &&
        buttonDeleteFile.addEventListener("click", () =>
          this.confirmDeleteFile(
            filesInputOld,
            filesFormHelper,
            buttonDeleteFile
          )
        );
    }
  }

  /**
   * Opens a confirmation dialog before deleting file data. Uses a custom dialog if provided,
   * otherwise defaults to `window.confirm`.
   *
   * @param {HTMLInputElement} filesInputOld - The hidden input storing file URLs.
   * @param {HTMLElement} filesFormHelper - The container displaying file links.
   * @param {HTMLElement} buttonDeleteFile - The delete button.
   */
  confirmDeleteFile(filesInputOld, filesFormHelper, buttonDeleteFile) {
    const text = "Deleted item cannot be recovered. Are you sure?";

    if (this.confirmDialog(text)) {
      filesInputOld.value = "";
      filesFormHelper.remove();
      buttonDeleteFile.remove();
    }
  }

  // Basic window confirmation
  confirmDialog = (text) => window.confirm(`${text}`);
}

// Usage example:
// const modalElement = document.getElementById('editKomponenMetaModal');
// const metaFieldPopulator = new MetaFieldPopulator(modalElement, {
//   // Optional custom confirmation dialog:
//   // confirmDialog: (title, text) => customConfirmationDialog(title, text)
// });
// metaFieldPopulator.populateEditKomponenMetaFields(metaDataArray);
