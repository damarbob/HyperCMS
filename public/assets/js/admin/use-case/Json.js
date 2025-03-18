// This helper function should be defined somewhere if you haven't already.
// For demonstration purposes: a simple validity check for JSON strings.
export function isValidJson(str) {
  try {
    JSON.parse(str);
    return true;
  } catch (e) {
    return false;
  }
}
