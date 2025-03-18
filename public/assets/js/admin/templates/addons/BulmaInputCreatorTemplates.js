import van from "https://cdn.jsdelivr.net/gh/vanjs-org/van/public/van-1.5.3.min.js"
import {replaceEnvironmentSyntax} from "../../use-case/EnvironmentSyntaxParser.js";

const {div, input, label, textarea, select, option, small, fragment, span, i} = van.tags;

/* ---------------------------------------------------------------------------
   Define Bulma-based template set.
--------------------------------------------------------------------------- */
export const bulmaInputCreatorTemplates = {
    text: ({id, nama, tipe, required, value, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                input({type: tipe, id, name: id, value: value || "", class: "input", required})
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    datetime: ({id, nama, tipe, required, value, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                input({type: tipe, id, name: id, value: value || "", class: "input", required})
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    color: ({id, nama, tipe, required, value, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                input({type: tipe, id, name: id, value: value || "", class: "input", title: nama, required})
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    textarea: ({id, nama, required, value, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                textarea({id, name: id, class: "textarea", required}, value || "")
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    editor: ({id, nama, required, value, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                textarea({id, name: id, class: "textarea hyper-rich-text-editor", required}, value || "")
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    checkbox: ({id, nama, required, checked, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            div({class: "control"},
                label({class: "checkbox"},
                    input({type: "checkbox", id, name: id, required, checked}),
                    ` ${nama}`
                )
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    checkboxes: ({id, options, required, keterangan}) => {
        if (!options || !Array.isArray(options)) return "";
        return div({class: "field"},
            div({class: "checkboxes"},
                options?.map(option =>
                    label({class: "checkbox"},
                        input({
                            type: "checkbox",
                            id: `${id}_${option.value}`,
                            name: `${id}[]`,
                            value: option.value,
                            required,
                            checked: option.checked
                        }),
                        label({for: `${id}_${option.value}`}, ` ${option.label || option.value}`)
                    )
                ),
                keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
            )
        );
    },

    radio: ({id, options, required, keterangan}) => {
        if (!options || !Array.isArray(options)) return "";
        return div({class: "field"},
            div({class: "radios"},
                options.map(option =>
                    label({class: "radio"},
                        input({
                            type: "radio",
                            id: `${id}_${option.value}`,
                            name: id,
                            value: option.value,
                            required,
                            checked: option.checked
                        }),
                        ` ${option.label || option.value}`
                    )
                ),
                keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
            )
        );
    },

    range: ({id, nama, required, value, options, keterangan}) => {
        return div({class: `field ${keterangan ? "mb-4" : "mb-3"}`},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                input({
                    type: "range",
                    id,
                    name: id,
                    value: value || "",
                    class: "slider",
                    min: options?.[0]?.min,
                    max: options?.[0]?.max,
                    required
                })
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    },

    file: ({id, nama, required, tipe, keterangan}) => {
        return div({class: "field", id: `${id}_parent`},
            label({class: "label", for: id}, nama),
            div(
                {class: "file has-name is-fullwidth"},
                label(
                    {class: "file-label"},
                    input({
                        class: "file-input",
                        type: "file",
                        id: id,
                        name: tipe === "file-multiple" ? `${id}[]` : id,
                        multiple: tipe === "file-multiple",
                        required
                    }),
                    input({type: "hidden", id: `${id}_old`, name: id}),
                    span(
                        {class: "file-cta"},
                        span({class: "file-icon"}, i({class: "fas fa-upload"})),
                        span({class: "file-label"}, "Choose a file…")
                    ),
                    span({class: "file-name"}),
                )
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan)),
            div({class: "is-flex is-flex-wrap-wrap mb-2", id: `${id}_formHelper`, style: "gap: 0.5rem"}),
        );
    },

    select: ({id, nama, required, value, options, keterangan}) => {
        if (!options || !Array.isArray(options)) return "";
        return div({class: "field"},
            label({class: "label", for: id}, nama),
            div({class: "control"},
                div({class: "select"},
                    select({id, name: id, required},
                        options.map(item => option({
                            value: item.value,
                            selected: value && item.value == value
                        }, item.label))
                    )
                )
            ),
            keterangan && p({class: "help"}, replaceEnvironmentSyntax(keterangan))
        );
    }
};
