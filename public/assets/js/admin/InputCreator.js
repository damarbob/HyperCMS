// InputCreator.js

import {InputCreatorTemplates} from "./templates/InputCreatorTemplates.js";
import {bulmaInputCreatorTemplates} from "./templates/addons/BulmaInputCreatorTemplates.js";
import {config} from "../Config.js";

export default class InputCreator {
    constructor(container) {
        this.container = container;
        InputCreatorTemplates.setTemplate(bulmaInputCreatorTemplates);
        this.requireMetaSelector = ".require-meta";
        if (!this.container) {
            throw new Error("Container not found.");
        }
    }

    /**
     * Unified method for creating inputs.
     * @param {Array} fields - Array of fields data.
     * @param {Boolean} wrapContent - Wrap each item inside an object with a content key.
     */
    createInputs(fields, wrapContent = false) {
        this.clearContainer();

        if (!fields || fields.length === 0) {
            this.displayNoMetaMessage();
            this.toggleMetaRequiredElements(false);
            return;
        }

        this.toggleMetaRequiredElements(true);
        const fragment = document.createDocumentFragment();

        fields.forEach((item) => {
            const content = wrapContent ? {content: item} : item;
            const html = this.createInputFromMetaItem(content, true);
            if (html) {
                const tempDiv = document.createElement("div");
                tempDiv.innerHTML = html;
                while (tempDiv.firstChild) {
                    fragment.appendChild(tempDiv.firstChild);
                }
                if (content.content.tipe === "editor") {
                    // @TODO: initializeTinyMCE(content.content.id);
                }
            }
        });

        this.container.appendChild(fragment);
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
        if (!item?.content) {
            console.error("Invalid fields content");
            return "";
        }

        const {id, nama, tipe, keterangan, options, required, value, checked} =
            item.content;

        let div = document.createElement("div");
        let field = document.createElement("div");

        switch (tipe) {
            case "text":
            case "email":
            case "password":
            case "number":
                field = InputCreatorTemplates.text({
                    id,
                    nama,
                    tipe,
                    required,
                    value,
                    keterangan,
                });
                break;
            case "datetime-local":
                field = InputCreatorTemplates.datetime({
                    id,
                    nama,
                    tipe,
                    required,
                    value,
                    keterangan,
                });
                break;
            case "color":
                field = InputCreatorTemplates.color({
                    id,
                    nama,
                    tipe,
                    required,
                    value,
                    keterangan,
                });
                break;
            case "textarea":
                field = InputCreatorTemplates.textarea({
                    id,
                    nama,
                    required,
                    value,
                    keterangan,
                });
                break;
            case "editor":
                // this.initializeTinyMCE(id);
                field = InputCreatorTemplates.editor({
                    id,
                    nama,
                    value,
                    keterangan,
                });
                break;
            case "checkbox":
                field = InputCreatorTemplates.checkbox({
                    id,
                    nama,
                    required,
                    checked,
                    keterangan,
                });
                break;
            case "checkboxes":
                field = InputCreatorTemplates.checkboxes({
                    id,
                    options,
                    required,
                    keterangan,
                });
                break;
            case "radio":
                field = InputCreatorTemplates.radio({
                    id,
                    options,
                    required,
                    keterangan,
                });
                break;
            case "range":
                field = InputCreatorTemplates.range({
                    id,
                    nama,
                    required,
                    value,
                    options,
                    keterangan,
                });
                break;
            case "file":
            case "file-multiple":
                field = InputCreatorTemplates.file({
                    id,
                    nama,
                    required,
                    tipe,
                    keterangan,
                });
                break;
            case "select":
                field = InputCreatorTemplates.select({
                    id,
                    nama,
                    required,
                    value,
                    options,
                    keterangan,
                });
                break;
            default:
                console.warn(`Unknown input type: ${tipe}`);
        }

        // console.log(field);
        // console.log(Array.isArray(field));

        if (Array.isArray(field)) {
            field.forEach((item) => {
                div.append(item);
            });
        } else {
            div.append(field);
        }

        return div.innerHTML;
    }

    /////////////////
}
