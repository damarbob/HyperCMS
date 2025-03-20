// InputTemplates.js
import van from "https://cdn.jsdelivr.net/gh/vanjs-org/van/public/van-1.5.3.min.js"
import {replaceEnvironmentSyntax} from "../use-case/EnvironmentSyntaxParser.js";

const {div, input, label, textarea, select, option, small, fragment} = van.tags;

/* ---------------------------------------------------------------------------
   Define the default (current) template set based on Bootstrap 5.
--------------------------------------------------------------------------- */
const defaultTemplates = {
    text: ({ id, nama, tipe, required, value, keterangan }) => {
        const container = document.createElement("div");
        container.className = `form-floating ${keterangan ? "mb-4" : "mb-3"}`;

        container.innerHTML = `
            <input
                type="${tipe}"
                id="${id}"
                name="${id}"
                value="${value || ""}"
                class="form-control"
                ${required ? "required" : ""}
            />
            <label class="form-label" for="${id}">${nama}</label>
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    datetime: ({ id, nama, tipe, required, value, keterangan }) => {
        const container = document.createElement("div");
        container.className = `form-outline ${keterangan ? "mb-4" : "mb-3"}`;

        container.innerHTML = `
            <input
                type="${tipe}"
                id="${id}"
                name="${id}"
                value="${value || ""}"
                class="form-control form-control-lg"
                ${required ? "required" : ""}
            />
            <label class="form-label" for="${id}">${nama}</label>
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    color: ({ id, nama, tipe, required, value, keterangan }) => {
        const container = document.createElement("div");
        container.className = keterangan ? "mb-4" : "mb-3";

        container.innerHTML = `
            <label class="form-label" for="${id}">${nama}</label>
            <input
                type="${tipe}"
                id="${id}"
                name="${id}"
                value="${value || "#000000"}"
                class="form-control form-control-color"
                title="${nama}"
                ${required ? "required" : ""}
            />
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    textarea: ({ id, nama, required, value, keterangan }) => {
        const container = document.createElement("div");
        container.className = `form-floating ${keterangan ? "mb-4" : "mb-3"}`;

        container.innerHTML = `
            <textarea
                id="${id}"
                name="${id}"
                class="form-control"
                ${required ? "required" : ""}
            >${value || ""}</textarea>
            <label class="form-label" for="${id}">${nama}</label>
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    editor: ({ id, nama, value, keterangan }) => {
        const container = document.createElement("div");
        container.className = keterangan ? "mb-4" : "mb-3";

        container.innerHTML = `
            <textarea
                id="${id}"
                name="${id}"
                class="form-control hyper-rich-text-editor"
            >${value || ""}</textarea>
            <label class="form-label" for="${id}">${nama}</label>
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    checkbox: ({ id, nama, required, checked, keterangan }) => {
        const container = document.createElement("div");
        container.className = `form-check ${keterangan ? "mb-4" : "mb-3"}`;

        container.innerHTML = `
            <input
                type="checkbox"
                id="${id}"
                name="${id}"
                class="form-check-input"
                ${required ? "required" : ""}
                ${checked ? "checked" : ""}
            />
            <label class="form-check-label" for="${id}">${nama}</label>
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    checkboxes: ({ id, options, required, keterangan }) => {
        if (!options || !Array.isArray(options)) return document.createDocumentFragment();

        const container = document.createElement("div");
        container.className = 'mb-3';

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
                    <label class="form-check-label" for="${id}_${option.value}">${option.label || option.value}</label>
                </div>
            `
            )
            .join("");

            
        container.innerHTML = checkboxesHTML;

        if (keterangan) {
            container.insertAdjacentHTML('beforeend', `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>`);
        }
          
        return container;
    },

    radio: ({ id, options, required }) => {
        if (!options || !Array.isArray(options)) return document.createDocumentFragment();

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
                    <label class="form-check-label" for="${id}_${option.value}">${option.label || option.value}</label>
                </div>
            `
            )
            .join("");

        container.innerHTML = radiosHTML;
        return container;
    },

    range: ({ id, nama, required, value, options, keterangan }) => {
        const container = document.createElement("div");

        container.innerHTML = `
            <label class="form-label" for="${id}">${nama}</label>
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
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },

    file: ({ id, nama, required, tipe, keterangan }) => {
        const container = document.createElement("div");
        container.className = "form-floating mb-3";
        container.id = `${id}_parent`;

        container.innerHTML = `
            <input
                type="file"
                id="${id}"
                name="${tipe === "file-multiple" ? `${id}[]` : id}"
                class="form-control"
                ${tipe === "file-multiple" ? "multiple" : ""}
                ${required ? "required" : ""}
            />
            <label class="form-label" for="${id}">${nama}</label>
            <input type="hidden" id="${id}_old" name="${id}" />
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
            <div class="d-flex flex-wrap gap-2 pt-2 pb-2" id="${id}_formHelper"></div>
        `;

        return container;
    },

    select: ({ id, nama, required, value, options, keterangan }) => {
        if (!options || !Array.isArray(options)) return document.createDocumentFragment();

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
            <label class="form-label" for="${id}">${nama}</label>
            <select id="${id}" name="${id}" class="form-select" ${required ? "required" : ""}>
                ${optionsHTML}
            </select>
            ${keterangan ? `<div class="form-helper"><small>${replaceEnvironmentSyntax(keterangan)}</small></div>` : ""}
        `;

        return container;
    },
};

let template = defaultTemplates;

/**
 * Sets the current template framework.
 * @param {{text: function({id: *, nama: *, tipe: *, required: *, value: *, keterangan: *}): *, datetime: function({id: *, nama: *, tipe: *, required: *, value: *, keterangan: *}): *, color: function({id: *, nama: *, tipe: *, required: *, value: *, keterangan: *}): *, textarea: function({id: *, nama: *, required: *, value: *, keterangan: *}): *, checkbox: function({id: *, nama: *, required: *, checked: *, keterangan: *}): *, radio: function({id: *, options: *, required: *}): (string|*), range: function({id: *, nama: *, required: *, value: *, options: *, keterangan: *}): *, file: function({id: *, nama: *, required: *, tipe: *, keterangan: *}): *, select: function({id: *, nama: *, required: *, value: *, options: *, keterangan: *}): (string|*)}} jsonTemplate - the JSON template
 */
const setTemplate = (jsonTemplate) => {
    template = jsonTemplate;
};

/* ---------------------------------------------------------------------------
   Export the unified InputTemplates API.
--------------------------------------------------------------------------- */
export const InputCreatorTemplates = {
    text: (data) => template.text(data),
    datetime: (data) => template.datetime(data),
    color: (data) => template.color(data),
    textarea: (data) => template.textarea(data),
    editor: (data) => template.editor(data),
    checkbox: (data) => template.checkbox(data),
    checkboxes: (data) => template.checkboxes(data),
    radio: (data) => template.radio(data),
    range: (data) => template.range(data),
    file: (data) => template.file(data),
    select: (data) => template.select(data),
    setTemplate,
};
