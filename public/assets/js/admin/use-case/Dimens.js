/**
 * Returns the height of the navbar element.
 *
 * @returns {number} The height of the element with the ID "navbar" in pixels.
 */
export function getNavbarHeight() {
  const navbarHeight = document.querySelector("#navbar").offsetHeight;
  return navbarHeight || 0; // Fallback to 0 if the element is not found
}
