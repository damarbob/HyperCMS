/**
 * Encodes a string into a hexadecimal representation.
 *
 * Each character in the input string is converted to its corresponding
 * two-digit hexadecimal code.
 *
 * @param {string} input - The string to encode.
 * @returns {string} The hex-encoded string.
 */
export function hexEncode(input) {
  return Array.from(input)
    .map((char) => char.charCodeAt(0).toString(16).padStart(2, "0"))
    .join("");
}

/**
 * Decodes a hex-encoded string back into a regular string.
 *
 * Assumes that the input string contains an even number of characters.
 *
 * @param {string} input - The hex-encoded string.
 * @returns {string} The decoded string.
 */
export function hexDecode(input) {
  let decoded = "";
  // Process every two characters, converting them back to a single character.
  for (let i = 0; i < input.length; i += 2) {
    const hexChunk = input.substr(i, 2);
    const charCode = parseInt(hexChunk, 16);
    decoded += String.fromCharCode(charCode);
  }
  return decoded;
}
