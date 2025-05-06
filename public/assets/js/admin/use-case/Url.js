/**
 * Compares two URIs for equality by normalizing them.
 *
 * This function uses the built-in URL API to create a URL object for each provided URI.
 * The URL constructor automatically normalizes the URL (e.g., resolves trailing slashes,
 * protocol differences, etc.), and the `.href` property returns this normalized string.
 * It then compares the normalized strings to determine if the two URIs are equivalent.
 *
 * **Note:** The function expects absolute URLs. If you provide a relative URL (without a
 * base), the URL constructor will throw an error.
 *
 * @param {string} uri1 - The first URI to compare.
 * @param {string} uri2 - The second URI to compare.
 * @returns {boolean} Returns `true` if the normalized URIs are exactly equal; otherwise, returns `false`.
 *
 * @example
 * // Both URIs are equivalent after normalization.
 * console.log(areURIsEqual("https://example.com", "https://example.com/")); // Might output true depending on normalization details.
 *
 * @example
 * // Different URIs result in false.
 * console.log(areURIsEqual("https://example.com/path", "https://example.com/other-path")); // Outputs false.
 */
export function areUrisEqual(uri1, uri2) {
  try {
    // Create a URL object for each input to normalize the string.
    const normalizedURI1 = new URL(uri1).href;
    const normalizedURI2 = new URL(uri2).href;
    return normalizedURI1 === normalizedURI2;
  } catch (error) {
    // If either URI is invalid, log the error and return false.
    console.error("Invalid URL:", error);
    return false;
  }
}
