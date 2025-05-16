/**
 * Replaces placeholders in a string with values from an object.
 * Placeholders are defined by curly braces, e.g., {x}, {y}, {z}.
 *
 * @param {string} str - The string containing placeholders.
 * @param {Object} replacements - An object mapping keys to replacement values.
 * @returns {string} The string with all placeholders replaced.
 */
export function replacePlaceholders(str, replacements) {
  return str.replace(/{([^}]+)}/g, (match, key) =>
    key in replacements ? replacements[key] : match
  );
}
