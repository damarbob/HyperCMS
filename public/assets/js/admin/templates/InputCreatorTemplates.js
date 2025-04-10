// InputTemplates.js
import { replaceEnvironmentSyntax } from "../use-case/EnvironmentSyntaxParser.js";

/* ---------------------------------------------------------------------------
   Define the default (current) template set based on Bootstrap 5.
--------------------------------------------------------------------------- */
const defaultTemplates = {
  hidden: ({ id, value }) => {
    const container = document.createElement("div");
    container.innerHTML = `<input type="hidden" id="${id}" name="${id}" value="${value}" />`;
    return container;
  },
  text: ({ id, label, type, required, value, helper, className }) => {
    const container = document.createElement("div");
    container.className = `form-floating ${helper ? "mb-4" : "mb-3"}`;

    container.innerHTML = `
            <input
                type="${type}"
                id="${id}"
                name="${id}"
                value="${value || ""}"
                class="form-control ${className}"
                ${required ? "required" : ""}
            />
            <label class="form-label" for="${id}">${label}</label>
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },

  datetime: ({ id, label, type, required, value, helper }) => {
    const container = document.createElement("div");
    container.className = `form-outline ${helper ? "mb-4" : "mb-3"}`;

    container.innerHTML = `
            <input
                type="${type}"
                id="${id}"
                name="${id}"
                value="${value || ""}"
                class="form-control form-control-lg"
                ${required ? "required" : ""}
            />
            <label class="form-label" for="${id}">${label}</label>
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },

  color: ({ id, label, type, required, value, helper }) => {
    const container = document.createElement("div");
    container.className = helper ? "mb-4" : "mb-3";

    container.innerHTML = `
            <label class="form-label" for="${id}">${label}</label>
            <input
                type="${type}"
                id="${id}"
                name="${id}"
                value="${value || "#000000"}"
                class="form-control form-control-color"
                title="${label}"
                ${required ? "required" : ""}
            />
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },

  textarea: ({ id, label, required, value, helper, className }) => {
    const container = document.createElement("div");
    container.className = `form-floating ${helper ? "mb-4" : "mb-3"}`;

    container.innerHTML = `
            <textarea
                id="${id}"
                name="${id}"
                class="form-control ${className}"
                ${required ? "required" : ""}
            >${value || ""}</textarea>
            <label class="form-label" for="${id}">${label}</label>
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },

  checkbox: ({ id, label, required, checked, helper }) => {
    const container = document.createElement("div");
    container.className = `form-check ${helper ? "mb-4" : "mb-3"}`;

    container.innerHTML = `
            <input
                type="checkbox"
                id="${id}"
                name="${id}"
                class="form-check-input"
                ${required ? "required" : ""}
                ${checked ? "checked" : ""}
            />
            <label class="form-check-label" for="${id}">${label}</label>
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },

  checkboxes: ({ id, options, required, helper }) => {
    if (!options || !Array.isArray(options))
      return document.createDocumentFragment();

    const container = document.createElement("div");
    container.className = "mb-3";

    const checkboxesHTML = options
      .map(
        (option) => `
                <div class="form-check">
                    <input
                        type="checkbox"
                        id="${id}_${option.value}"
                        name="${id}[]"
                        value="${option.value}"
                        class="form-check-input"
                        ${required ? "required" : ""}
                        ${option.checked ? "checked" : ""}
                    />
                    <label class="form-check-label" for="${id}_${
          option.value
        }">${option.label || option.value}</label>
                </div>
            `
      )
      .join("");

    container.innerHTML = checkboxesHTML;

    if (helper) {
      container.insertAdjacentHTML(
        "beforeend",
        `<div class="form-helper"><small>${replaceEnvironmentSyntax(
          helper
        )}</small></div>`
      );
    }

    return container;
  },

  radio: ({ id, options, required }) => {
    if (!options || !Array.isArray(options))
      return document.createDocumentFragment();

    const container = document.createElement("div");

    const radiosHTML = options
      .map(
        (option) => `
                <div class="form-check mb-3">
                    <input
                        type="radio"
                        id="${id}_${option.value}"
                        name="${id}"
                        value="${option.value}"
                        class="form-check-input"
                        ${required ? "required" : ""}
                        ${option.checked ? "checked" : ""}
                    />
                    <label class="form-check-label" for="${id}_${
          option.value
        }">${option.label || option.value}</label>
                </div>
            `
      )
      .join("");

    container.innerHTML = radiosHTML;
    return container;
  },

  range: ({ id, label, required, value, options, helper }) => {
    const container = document.createElement("div");

    container.innerHTML = `
            <label class="form-label" for="${id}">${label}</label>
            <div class="range mb-3">
                <input
                    type="range"
                    id="${id}"
                    name="${id}"
                    value="${value || ""}"
                    class="form-range"
                    min="${options?.[0]?.min || 0}"
                    max="${options?.[0]?.max || 100}"
                    ${required ? "required" : ""}
                />
            </div>
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },

  file: ({ id, label, required, helper, multiple }) => {
    const container = document.createElement("div");
    container.className = "form-floating mb-3";
    container.id = `${id}_parent`;

    container.innerHTML = `
            <input
                type="file"
                id="${id}"
                name="${multiple ? `${id}[]` : id}"
                class="form-control"
                ${multiple ? "multiple" : ""}
                ${required ? "required" : ""}
            />
            <label class="form-label" for="${id}">${label}</label>
            <input type="hidden" id="${id}_old" name="${id}" />
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
            <div class="d-flex flex-wrap gap-2 pt-2 pb-2" id="${id}_form-helper"></div>
        `;

    return container;
  },

  select: ({ id, label, required, value, options, helper }) => {
    if (!options || !Array.isArray(options))
      return document.createDocumentFragment();

    const container = document.createElement("div");
    container.className = "mb-3";

    const optionsHTML = options
      .map(
        (item) => `
                <option
                    value="${item.value}"
                    ${value && item.value == value ? "selected" : ""}
                >
                    ${item.label}
                </option>
            `
      )
      .join("");

    container.innerHTML = `
            <label class="form-label" for="${id}">${label}</label>
            <select id="${id}" name="${id}" class="form-select" ${
      required ? "required" : ""
    }>
                ${optionsHTML}
            </select>
            ${
              helper
                ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(
                    helper
                  )}</small></div>`
                : ""
            }
        `;

    return container;
  },
};

let template = defaultTemplates;

/**
 * Sets the current template framework.
 */
const setTemplate = (jsonTemplate) => {
  template = jsonTemplate;
};

/* ---------------------------------------------------------------------------
   Export the unified InputTemplates API.
--------------------------------------------------------------------------- */
export const InputCreatorTemplates = {
  hidden: (data) => template.hidden?.(data) ?? defaultTemplates.hidden(data),
  text: (data) => template.text(data),
  datetime: (data) => template.datetime(data),
  color: (data) => template.color(data),
  textarea: (data) => template.textarea(data),
  editor: (data) => template.editor(data),
  code: (data) => template.code(data),
  checkbox: (data) => template.checkbox(data),
  checkboxes: (data) => template.checkboxes(data),
  radio: (data) => template.radio(data),
  range: (data) => template.range(data),
  file: (data) => template.file(data),
  select: (data) => template.select(data),
  setTemplate,
};
