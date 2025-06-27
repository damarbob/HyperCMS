export default class InputHandler {
  // Initialize input handler with form, input, and submit button elements
  constructor({ form, input, submitButton }) {
    this.form = form;
    this.input = input;
    this.submitButton = submitButton;
  }

  // Attach keydown and input listeners to the textarea
  init() {
    this.input.addEventListener("keydown", this.handleKeydown.bind(this));
    this.input.addEventListener("input", this.autoResize.bind(this));
  }

  // Submit the form when Enter is pressed without Shift
  handleKeydown(e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      this.input.form.dispatchEvent(new Event("submit"));
    }
  }

  // Resize the textarea height automatically based on its content
  autoResize() {
    this.input.style.height = "auto";
    this.input.style.height = `${this.input.scrollHeight}px`;
  }

  // Disable the input and show a loading indicator on the submit button
  disable() {
    this.input.disabled = true;
    this.submitButton.classList.add("is-loading");
  }

  // Re-enable the input, hide the loading indicator, and focus the field
  enable() {
    this.input.disabled = false;
    this.submitButton.classList.remove("is-loading");
    this.input.focus();
  }
}
