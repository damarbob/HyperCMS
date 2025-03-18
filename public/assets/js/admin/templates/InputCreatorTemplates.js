// InputTemplates.js
import van from "https://cdn.jsdelivr.net/gh/vanjs-org/van/public/van-1.5.3.min.js"
import {replaceEnvironmentSyntax} from "../use-case/EnvironmentSyntaxParser.js";

const {div, input, label, textarea, select, option, small, fragment} = van.tags;

/* ---------------------------------------------------------------------------
   Define the default (current) template set based on Bootstrap 5.
--------------------------------------------------------------------------- */
const defaultTemplates = {
    text: ({ id, nama, tipe, required, value, keterangan }) => {
        return div({ class: `form-floating ${keterangan ? "mb-4" : "mb-3"}` },
            input({ type: tipe, id, name: id, value: value || "", class: "form-control", required }),
            label({ class: "form-label", for: id }, nama),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    datetime: ({ id, nama, tipe, required, value, keterangan }) => {
        return div({ class: `form-outline ${keterangan ? "mb-4" : "mb-3"}` },
            input({ type: tipe, id, name: id, value: value || "", class: "form-control form-control-lg", required }),
            label({ class: "form-label", for: id }, nama),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    color: ({ id, nama, tipe, required, value, keterangan }) => {
        return div({ class: keterangan ? "mb-4" : "mb-3" },
            label({ class: "form-label", for: id }, nama),
            input({ type: tipe, id, name: id, value: value || "", class: "form-control form-control-color", title: nama, required }),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    textarea: ({ id, nama, required, value, keterangan }) => {
        return div({ class: `form-floating ${keterangan ? "mb-4" : "mb-3"}` },
            textarea({ id, name: id, class: "form-control", required }, value || ""),
            label({ class: "form-label", for: id }, nama),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    editor: ({ id, nama, value, keterangan }) => {
        return div({ class: keterangan ? "mb-4" : "mb-3" },
            textarea({ id, name: id, class: "form-control hyper-rich-text-editor" }, value || ""),
            label({ class: "form-label", for: id }, nama),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    checkbox: ({ id, nama, required, checked, keterangan }) => {
        return div({ class: `form-check ${keterangan ? "mb-4" : "mb-3"}` },
            input({ type: "checkbox", id, name: id, class: "form-check-input", required, checked }),
            label({ class: "form-check-label", for: id }, nama),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    checkboxes: ({ id, options, required }) => {
        if (!options || !Array.isArray(options)) return "";
        return options?.map(option =>
            div({ class: "form-check mb-3" },
                input({ type: "checkbox", id: `${id}_${option.value}`, name: `${id}[]`, value: option.value, class: "form-check-input", required, checked: option.checked }),
                label({ class: "form-check-label", for: `${id}_${option.value}` }, option.label || option.value)
            )
        );
    },

    radio: ({ id, options, required }) => {
        if (!options || !Array.isArray(options)) return "";
        return options?.map(option =>
            div({ class: "form-check mb-3" },
                input({ type: "radio", id: `${id}_${option.value}`, name: id, value: option.value, class: "form-check-input", required, checked: option.checked }),
                label({ class: "form-check-label", for: `${id}_${option.value}` }, option.label || option.value)
            )
        );
    },

    range: ({ id, nama, required, value, options, keterangan }) => {
        return fragment(
            label({ class: "form-label", for: id }, nama),
            div({ class: "range mb-3" },
                input({ type: "range", id, name: id, value: value || "", class: "form-range", min: options?.[0]?.min, max: options?.[0]?.max, required })
            ),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    },

    file: ({ id, nama, required, tipe, keterangan }) => {
        return div({ class: "form-floating mb-3", id: `${id}_parent` },
            input({ type: "file", id, name: tipe === "file-multiple" ? `${id}[]` : id, class: "form-control", multiple: tipe === "file-multiple", required }),
            label({ class: "form-label", for: id }, nama),
            input({ type: "hidden", id: `${id}_old`, name: id }),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan))),
            div({ class: "d-flex flex-wrap gap-2 pt-2 pb-2 ", id: `${id}_formHelper` }),
        );
    },

    select: ({ id, nama, required, value, options, keterangan }) => {
        if (!options || !Array.isArray(options)) return "";
        return div({ class: "mb-3" },
            label({ class: "form-label", for: id }, nama),
            select({ id, name: id, class: "form-select", required },
                options.map(item => option({ value: item.value, selected: value && item.value == value }, item.label))
            ),
            keterangan && div({ class: "form-helper" }, small(replaceEnvironmentSyntax(keterangan)))
        );
    }
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
