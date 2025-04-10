// InputCreator.js

import { InputCreatorTemplates } from "./templates/InputCreatorTemplates.js";
import { bulmaInputCreatorTemplates } from "./templates/addons/BulmaInputCreatorTemplates.js";
import { config } from "../Config.js";

export default class InputCreator {
  constructor({
    container,
    onFieldCreated = (fieldId) => {},
    onFieldsCreated = (fieldIds) => {},
  }) {
    this.container = container;
    this.onFieldCreated = onFieldCreated;
    this.onFieldsCreated = onFieldsCreated;
    InputCreatorTemplates.setTemplate(bulmaInputCreatorTemplates);
    this.requireMetaSelector = ".require-meta";
    if (!this.container) {
      throw new Error("Container not found.");
    }
  }

  /**
   * Unified method for creating inputs.
   * @param {Array} fields - Array of fields data.
   */
  createInputs(fields) {
    this.clearContainer();

    if (!fields || fields.length === 0) {
      this.displayNoMetaMessage();
      this.toggleMetaRequiredElements(false);
      return;
    }

    this.toggleMetaRequiredElements(true);
    const fragment = document.createDocumentFragment();

    // Create inputs based on the fields data
    const fieldIds = [];

    fields.forEach((item) => {
      const html = this.createInputFromMetaItem(item, true);
      if (html) {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = html;
        while (tempDiv.firstChild) {
          fragment.appendChild(tempDiv.firstChild);
        }
        fieldIds.push(item.id);
        this.onFieldCreated(item.id);
      }
    });

    this.container.appendChild(fragment);
    this.onFieldsCreated(fieldIds);
  }

  // Backward-compatible methods
  createV1(fields) {
    this.createInputs(fields, true);
  }

  create(fields) {
    this.createInputs(fields, false);
  }

  clearContainer() {
    this.container.innerHTML = "";
  }

  displayNoMetaMessage() {
    this.container.insertAdjacentHTML(
      "beforeend",
      `<p>Entry has empty fields</p>` // @TODO: Localization
    );
  }

  toggleMetaRequiredElements(show) {
    document.querySelectorAll(this.requireMetaSelector).forEach((el) => {
      el.classList.toggle("d-none", !show);
    });
  }

  /**
   * Creates input HTML for a fields item.
   * @param {Object} item - Meta data object.
   * @param {Boolean} returnHTML - If true, returns the HTML string.
   */
  createInputFromMetaItem(item, returnHTML = false) {
    const {
      id,
      label,
      type,
      helper,
      options,
      required,
      value,
      checked,
      className,
      multiple,
    } = item;

    let div = document.createElement("div");
    let field = document.createElement("div");

    switch (type) {
      case "hidden":
        field = InputCreatorTemplates.hidden({
          id,
        });
        break;
      case "text":
      case "email":
      case "password":
      case "number":
      case "url":
        field = InputCreatorTemplates.text({
          id,
          label,
          type,
          required,
          value,
          helper,
          className,
        });
        break;
      case "datetime-local":
        field = InputCreatorTemplates.datetime({
          id,
          label,
          type,
          required,
          value,
          helper,
        });
        break;
      case "color":
        field = InputCreatorTemplates.color({
          id,
          label,
          type,
          required,
          value,
          helper,
        });
        break;
      case "textarea":
        field = InputCreatorTemplates.textarea({
          id,
          label,
          required,
          value,
          helper,
          className,
        });
        break;
      case "checkbox":
        field = InputCreatorTemplates.checkbox({
          id,
          label,
          required,
          checked,
          helper,
        });
        break;
      case "checkboxes":
        field = InputCreatorTemplates.checkboxes({
          id,
          options,
          required,
          helper,
        });
        break;
      case "radio":
        field = InputCreatorTemplates.radio({
          id,
          options,
          required,
          helper,
        });
        break;
      case "range":
        field = InputCreatorTemplates.range({
          id,
          label,
          required,
          value,
          options,
          helper,
        });
        break;
      case "file":
        field = InputCreatorTemplates.file({
          id,
          label,
          required,
          helper,
          multiple,
        });
        break;
      case "select":
        field = InputCreatorTemplates.select({
          id,
          label,
          required,
          value,
          options,
          helper,
        });
        break;
      default:
        console.warn(`Unknown input type: ${type}`);
    }

    if (Array.isArray(field)) {
      field.forEach((item) => {
        div.append(item);
      });
    } else {
      div.append(field);
    }

    return div.innerHTML;
  }
}
