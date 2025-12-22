import { isValidJson } from "./Json.js";

/**
 * Encodes the form inputs into JSON and appends it into a FormData object.
 *
 * @param {string} name - The key under which the JSON data will be stored.
 * @param {HTMLFormElement} form - The form element to scan for inputs.
 * @param {FormData} [formData] - Optional existing FormData object. Defaults to new FormData().
 * @returns {FormData} The FormData object with the appended JSON data.
 */
export function encodeFormInputsToJson(name = "meta", form, formData) {
  formData = formData || new FormData();

  // console.log(name, form, formData);

  const inputs = form.querySelectorAll("input, textarea, select");
  let meta = {};
  let processedNames = new Set(); // For radio button group names

  inputs.forEach((input) => {
    // Destructure the properties we need
    const {
      id,
      type,
      name: inputName,
      value,
      files: inputFiles,
      checked,
    } = input;
    if (!id) return; // Skip if no id

    // Handle file inputs
    if (type === "file") {
      if (input.hasAttribute("multiple")) {
        if (inputFiles && inputFiles.length > 0) {
          for (let i = 0; i < inputFiles.length; i++) {
            formData.append(inputName, inputFiles[i]);
          }
          const fileArray = Array.from(inputFiles).map((file) => file.name);
          meta[id] = fileArray;
        } else {
          const oldFiles = document.getElementById(id + "_old").value;
          meta[id] = isValidJson(oldFiles) ? JSON.parse(oldFiles) : "";
        }
      } else {
        if (inputFiles && inputFiles.length > 0) {
          formData.append(inputName, inputFiles[0]);
          const fileArray = Array.from(inputFiles).map((file) => file.name);
          meta[id] = fileArray;
        } else {
          const oldFiles = document.getElementById(id + "_old").value;
          meta[id] = isValidJson(oldFiles) ? JSON.parse(oldFiles) : "";
        }
      }
    }
    // Handle radio buttons: only add the checked one and avoid duplicates
    else if (type === "radio") {
      if (checked && !processedNames.has(inputName)) {
        meta[inputName] = value;
        processedNames.add(inputName);
      }
    }
    // Handle checkboxes
    else if (type === "checkbox") {
      if (inputName.endsWith("[]")) {
        if (!checked) return;
        const originalName = inputName.slice(0, -2);
        if (meta[originalName]) {
          if (Array.isArray(meta[originalName])) {
            meta[originalName].push(value);
          } else {
            meta[originalName] = [meta[originalName], value];
          }
        } else {
          meta[originalName] = value;
        }
      } else {
        meta[id] = checked ? "on" : "off";
      }
    }
    // Skip hidden inputs
    else if (type === "hidden") {
      // Do nothing
    }
    // Handle all other input types (text, textarea, select, etc.)
    else {
      meta[id] = value;
    }
  });

  // Append the JSON-encoded meta-data to the FormData
  formData.append(name, JSON.stringify(meta));

  return formData;
}
