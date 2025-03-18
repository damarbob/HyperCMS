import { isValidJson } from "./Json.js";

FormData.prototype.encodeFormInputsToJson = function (name = "meta", form) {
  const inputs = form.querySelectorAll("input, textarea, select");
  let meta = [];
  let formData = this;
  let processedNames = new Set(); // To keep track of processed radio button groups

  inputs.forEach((input) => {
    // Destructure the needed properties and avoid name conflicts
    const {
      id,
      type,
      name: inputName,
      value,
      files: inputFiles,
      checked,
    } = input;

    if (!id) return; // Skip inputs without IDs

    // Handle file inputs
    if (type === "file") {
      if (input.hasAttribute("multiple")) {
        // Handle multiple files
        if (inputFiles && inputFiles.length > 0) {
          for (let i = 0; i < inputFiles.length; i++) {
            formData.append(name, inputFiles[i]); // Append files (use the function parameter "name")
          }
          const fileArray = Array.from(inputFiles).map((file) => file.name);
          meta.push({
            id,
            value: fileArray,
          });
        } else {
          const oldFiles = document.getElementById(id + "_old").value;
          meta.push({
            id,
            value: isValidJson(oldFiles) ? JSON.parse(oldFiles) : "",
          });
        }
      } else {
        // Handle single file
        if (inputFiles && inputFiles.length > 0) {
          formData.append(name, inputFiles[0]);
          const fileArray = Array.from(inputFiles).map((file) => file.name);
          meta.push({
            id,
            value: fileArray,
          });
        } else {
          const oldFiles = document.getElementById(id + "_old").value;
          meta.push({
            id,
            value: isValidJson(oldFiles) ? JSON.parse(oldFiles) : "",
          });
        }
      }
    }
    // Handle radio buttons - only add the checked one, avoid duplicates
    else if (type === "radio") {
      if (checked && !processedNames.has(inputName)) {
        meta.push({
          id: inputName,
          value,
        }); // Use inputName to group radio buttons
        processedNames.add(inputName);
      }
    }
    // Handle checkboxes
    else if (type === "checkbox") {
      if (inputName.endsWith("[]")) {
        // If unchecked, skip this checkbox
        if (!checked) {
          return;
        }

        let originalName = inputName.slice(0, -2); // Original name without the "[]"

        // Check if an object with the same id already exists
        let existingItem = meta.find((item) => item.id === originalName);

        if (existingItem) {
          // If the value property is already an array, append the new value
          if (Array.isArray(existingItem.value)) {
            existingItem.value.push(value);
          } else {
            // Convert to an array and add the new value
            existingItem.value = [existingItem.value, value];
          }
        } else {
          meta.push({
            id: originalName,
            value: value,
          });
        }
      } else {
        meta.push({
          id,
          value: checked ? "on" : "off",
        });
      }
    }
    // Handle hidden inputs (do nothing for hidden inputs)
    else if (type === "hidden") {
      // Do nothing
    }
    // Handle other inputs
    else {
      meta.push({
        id,
        value,
      });
    }
  });

  // Add the encoded JSON to FormData for sending to the server
  formData.append(name, JSON.stringify(meta));
};
