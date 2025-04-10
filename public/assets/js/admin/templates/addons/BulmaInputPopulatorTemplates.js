import van from "https://cdn.jsdelivr.net/gh/vanjs-org/van/public/van-1.5.3.min.js"
const {a, div, button, label, textarea, select, option, small, fragment} = van.tags;

const createSVG = (attributes, pathData, fillColor) => {
    const svgEl = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    const pathEl = document.createElementNS("http://www.w3.org/2000/svg", "path");

    // Set attributes for SVG
    for (const [key, value] of Object.entries(attributes)) {
        svgEl.setAttribute(key, value);
    }

    // Set attributes for Path
    pathEl.setAttribute("d", pathData);
    if (fillColor) {
        pathEl.setAttribute("fill", fillColor);
    }

    // Append path to SVG
    svgEl.appendChild(pathEl);
    return svgEl;
};

export const bulmaInputPopulatorTemplates = {
    fileLink: ({url, filename}) => {
        const svgAttributes = {
            class: "ml-2",
            width: "12px",
            xmlns: "http://www.w3.org/2000/svg",
            viewBox: "0 0 512 512",
        };

        const pathData =
            "M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l82.7 0L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3l0 82.7c0 17.7 14.3 32 32 32s32-14.3 32-32l0-160c0-17.7-14.3-32-32-32L320 0zM80 32C35.8 32 0 67.8 0 112L0 432c0 44.2 35.8 80 80 80l320 0c44.2 0 80-35.8 80-80l0-112c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 112c0 8.8-7.2 16-16 16L80 448c-8.8 0-16-7.2-16-16l0-320c0-8.8 7.2-16 16-16l112 0c17.7 0 32-14.3 32-32s-14.3-32-32-32L80 32z";

        return div({class: ""},
            a({href: url, target: "_blank", class: "button is-small"},
                // button({class: ""},
                filename,
                createSVG(svgAttributes, pathData, "var(--bulma-text-strong)")
                // ),
            )
        );
    },
    deleteFile: ({id}) => {
        const svgAttributes = {
            width: "12px",
            xmlns: "http://www.w3.org/2000/svg",
            viewBox: "0 0 448 512",
        };

        const pathData =
            "M135.2 17.7C140.6 6.8 151.7 0 163.8 0L284.2 0c12.1 0 23.2 6.8 28.6 17.7L320 32l96 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 96C14.3 96 0 81.7 0 64S14.3 32 32 32l96 0 7.2-14.3zM32 128l384 0 0 320c0 35.3-28.7 64-64 64L96 512c-35.3 0-64-28.7-64-64l0-320zm96 64c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16s16-7.2 16-16l0-224c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16s16-7.2 16-16l0-224c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16l0 224c0-8.8-7.2 16 16 16s16-7.2 16-16l0-224c0-8.8-7.2-16-16-16z";

        return button({type: "button", class: "delete", id: `${id}_button-delete-file`},
            // createSVG(svgAttributes, pathData, "var(--bs-body-bg)")
        );
    },
};