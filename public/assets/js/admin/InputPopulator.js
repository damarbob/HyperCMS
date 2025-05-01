// InputPopulator.js

import { config } from "../Config.js";
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
   * @param {Array} meta - An array of meta data objects.
   */
  populate(meta) {
    if (!meta) return;
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
   * Populates a NodeList (e.g. checkboxes or radio buttons) based on matching values.
   *
   * @param {NodeList} nodeList - The NodeList to populate.
   * @param {*} value - The value(s) to check.
   */
  populateNodeList(nodeList, value) {
    nodeList.forEach((element) => {
      if (!Array.isArray(value) && element.value === value) {
        element.checked = true;
      } else if (Array.isArray(value)) {
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
        filesFormHelper.append(
          InputPopulatorTemplates.fileLink({
            url: config.baseUrl + fileUrl,
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
