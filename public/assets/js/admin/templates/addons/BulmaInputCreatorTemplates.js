import { replaceEnvironmentSyntax } from "../../use-case/EnvironmentSyntaxParser.js";

/* ---------------------------------------------------------------------------
   Define Bulma-based template set.
--------------------------------------------------------------------------- */
export const bulmaInputCreatorTemplates = {
  text: ({ id, label, type, required, value, helper, className, options }) => {
    const field = document.createElement("div");
    field.className = `field ${helper ? "mb-4" : "mb-3"}`;

    field.innerHTML = `
            <label class="label" for="${id}">${label}</label>
            <div class="control">
                <input
                    type="${type}"
                    id="${id}"
                    name="${id}"
                    class="input ${className}"
                    value="${value || ""}"
                    ${required ? "required" : ""}
                    ${
                      options && options.step
                        ? "step='" + options.step + "'"
                        : ""
                    }
                    ${options && options.min ? "min='" + options.min + "'" : ""}
                    ${options && options.max ? "max='" + options.max + "'" : ""}
                />
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  datetime: ({ id, label, type, required, value, helper }) => {
    const field = document.createElement("div");
    field.className = `field ${helper ? "mb-4" : "mb-3"}`;

    field.innerHTML = `
            <label class="label" for="${id}">${label}</label>
            <div class="control">
                <input
                    type="${type}"
                    id="${id}"
                    name="${id}"
                    class="input"
                    value="${value || ""}"
                    ${required ? "required" : ""}
                />
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  color: ({ id, label, type, required, value, helper }) => {
    const field = document.createElement("div");
    field.className = `field ${helper ? "mb-4" : "mb-3"}`;

    field.innerHTML = `
            <label class="label" for="${id}">${label}</label>
            <div class="control">
                <input
                    type="${type}"
                    id="${id}"
                    name="${id}"
                    class="input"
                    title="${label}"
                    value="${value || "#000000"}"
                    ${required ? "required" : ""}
                />
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  textarea: ({ id, label, required, value, helper, className, data = {} }) => {
    // Build data-* attributes string
    let dataAttrs = "";
    for (const key in data) {
      if (data.hasOwnProperty(key)) {
        const attrName = `data-${key}`;
        const attrValue = String(data[key]).replace(/"/g, "&quot;");
        dataAttrs += ` ${attrName}="${attrValue}"`;
      }
    }

    const field = document.createElement("div");
    field.className = `field ${helper ? "mb-4" : "mb-3"}`;

    field.innerHTML = `
    <label class="label" for="${id}">${label}</label>
    <div class="control">
      <textarea
        id="${id}"
        name="${id}"
        class="textarea ${className || ""}"
        ${required ? "required" : ""}
        ${dataAttrs}
      >${value || ""}</textarea>
    </div>
    ${helper ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>` : ""}
  `.trim();

    return field;
  },

  checkbox: ({ id, label, required, checked, helper }) => {
    const field = document.createElement("div");
    field.className = `field ${helper ? "mb-4" : "mb-3"}`;

    field.innerHTML = `
            <div class="control">
                <label class="checkbox">
                    <input
                        type="checkbox"
                        id="${id}"
                        name="${id}"
                        ${required ? "required" : ""}
                        ${checked ? "checked" : ""}
                    />
                    ${label}
                </label>
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  checkboxes: ({ id, options, required, helper }) => {
    if (!options || !Array.isArray(options))
      return document.createDocumentFragment();

    const field = document.createElement("div");
    field.className = "field";

    const checkboxesHTML = options
      .map(
        (option) => `
                <label class="checkbox">
                    <input
                        type="checkbox"
                        id="${id}_${option.value}"
                        name="${id}[]"
                        value="${option.value}"
                        ${required ? "required" : ""}
                        ${option.checked ? "checked" : ""}
                    />
                    ${option.label || option.value}
                </label>
            `
      )
      .join("");

    field.innerHTML = `
            <div class="checkboxes">
                ${checkboxesHTML}
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  radio: ({ id, options, required, helper }) => {
    if (!options || !Array.isArray(options))
      return document.createDocumentFragment();

    const field = document.createElement("div");
    field.className = "field";

    const radiosHTML = options
      .map(
        (option) => `
                <label class="radio">
                    <input
                        type="radio"
                        id="${id}_${option.value}"
                        name="${id}"
                        value="${option.value}"
                        ${required ? "required" : ""}
                        ${option.checked ? "checked" : ""}
                    />
                    ${option.label || option.value}
                </label>
            `
      )
      .join("");

    field.innerHTML = `
            <div class="radios">
                ${radiosHTML}
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  range: ({ id, label, required, value, options, helper }) => {
    const field = document.createElement("div");
    field.className = `field ${helper ? "mb-4" : "mb-3"}`;

    field.innerHTML = `
            <label class="label" for="${id}">${label}</label>
            <div class="control">
                <input
                    type="range"
                    id="${id}"
                    name="${id}"
                    class="slider"
                    value="${value || ""}"
                    min="${options?.[0]?.min || 0}"
                    max="${options?.[0]?.max || 100}"
                    ${required ? "required" : ""}
                />
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },

  file: ({ id, label, required, helper, multiple }) => {
    const field = document.createElement("div");
    field.id = `${id}_parent`;
    field.className = "field";

    field.innerHTML = `
            <label class="label" for="${id}">${label}</label>
            <div class="file has-name is-fullwidth">
                <label class="file-label">
                    <input
                        class="file-input"
                        type="file"
                        id="${id}"
                        name="${multiple ? `${id}[]` : id}"
                        ${multiple ? "multiple" : ""}
                        ${required ? "required" : ""}
                    />
                    <input type="hidden" id="${id}_old" name="${id}" />
                    <span class="file-cta">
                        <span class="file-icon"><i class="fas fa-upload"></i></span>
                        <span class="file-label">Choose a file…</span>
                    </span>
                    <span class="file-name"></span>
                </label>
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
            <div class="is-flex is-flex-wrap-wrap mb-2" id="${id}_form-helper" style="gap: 0.5rem;"></div>
        `;

    return field;
  },

  select: ({ id, label, required, value, options, helper }) => {
    if (!options || !Array.isArray(options))
      return document.createDocumentFragment();

    const field = document.createElement("div");
    field.className = "field";

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

    field.innerHTML = `
            <label class="label" for="${id}">${label}</label>
            <div class="control">
                <div class="select">
                    <select id="${id}" name="${id}" ${
      required ? "required" : ""
    }>
                        ${optionsHTML}
                    </select>
                </div>
            </div>
            ${
              helper
                ? `<p class="help">${replaceEnvironmentSyntax(helper)}</p>`
                : ""
            }
        `;

    return field;
  },
};
