import {replaceEnvironmentSyntax} from "../../use-case/EnvironmentSyntaxParser.js";

/* ---------------------------------------------------------------------------
   Define Bulma-based template set.
--------------------------------------------------------------------------- */
export const bulmaInputCreatorTemplates = {
    text: ({ id, nama, tipe, required, value, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
            <div class="control">
                <input
                    type="${tipe}"
                    id="${id}"
                    name="${id}"
                    class="input"
                    value="${value || ""}"
                    ${required ? "required" : ""}
                />
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    datetime: ({ id, nama, tipe, required, value, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
            <div class="control">
                <input
                    type="${tipe}"
                    id="${id}"
                    name="${id}"
                    class="input"
                    value="${value || ""}"
                    ${required ? "required" : ""}
                />
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    color: ({ id, nama, tipe, required, value, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
            <div class="control">
                <input
                    type="${tipe}"
                    id="${id}"
                    name="${id}"
                    class="input"
                    title="${nama}"
                    value="${value || "#000000"}"
                    ${required ? "required" : ""}
                />
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    textarea: ({ id, nama, required, value, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
            <div class="control">
                <textarea
                    id="${id}"
                    name="${id}"
                    class="textarea"
                    ${required ? "required" : ""}
                >${value || ""}</textarea>
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    editor: ({ id, nama, required, value, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
            <div class="control">
                <textarea
                    id="${id}"
                    name="${id}"
                    class="textarea hyper-rich-text-editor"
                    ${required ? "required" : ""}
                >${value || ""}</textarea>
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    checkbox: ({ id, nama, required, checked, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

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
                    ${nama}
                </label>
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    checkboxes: ({ id, options, required, keterangan }) => {
        if (!options || !Array.isArray(options)) return document.createDocumentFragment();

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
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    radio: ({ id, options, required, keterangan }) => {
        if (!options || !Array.isArray(options)) return document.createDocumentFragment();

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
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    range: ({ id, nama, required, value, options, keterangan }) => {
        const field = document.createElement("div");
        field.className = `field ${keterangan ? "mb-4" : "mb-3"}`;

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
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
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },

    file: ({ id, nama, required, tipe, keterangan }) => {
        const field = document.createElement("div");
        field.id = `${id}_parent`;
        field.className = "field";

        field.innerHTML = `
            <label class="label" for="${id}">${nama}</label>
            <div class="file has-name is-fullwidth">
                <label class="file-label">
                    <input
                        class="file-input"
                        type="file"
                        id="${id}"
                        name="${tipe === "file-multiple" ? `${id}[]` : id}"
                        ${tipe === "file-multiple" ? "multiple" : ""}
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
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
            <div class="is-flex is-flex-wrap-wrap mb-2" id="${id}_formHelper" style="gap: 0.5rem;"></div>
        `;

        return field;
    },

    select: ({ id, nama, required, value, options, keterangan }) => {
        if (!options || !Array.isArray(options)) return document.createDocumentFragment();

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
            <label class="label" for="${id}">${nama}</label>
            <div class="control">
                <div class="select">
                    <select id="${id}" name="${id}" ${required ? "required" : ""}>
                        ${optionsHTML}
                    </select>
                </div>
            </div>
            ${keterangan ? `<p class="help">${replaceEnvironmentSyntax(keterangan)}</p>` : ""}
        `;

        return field;
    },
};
