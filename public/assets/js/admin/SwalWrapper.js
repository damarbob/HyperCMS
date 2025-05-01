import Swal from "https://cdn.jsdelivr.net/npm/sweetalert2@11.19.1/+esm";
import i18next from "https://deno.land/x/i18next/index.js";

/**
 * Wrapper class for SweetAlert2.
 *
 * This class provides a set of convenience methods to display
 * different types of alerts and toast notifications with consistent
 * options, themes, and loading behavior. It supports success,
 * error, confirmation, and prompt dialogs.
 *
 * In traditional scripts, you can bind and call this via the global:
 *
 *   document.addEventListener('DOMContentLoaded', function() {
 *     window.hyper_swal.success("I'm success");
 *   });
 *
 * In modules, import it and then call:
 *
 *   import { swalWrapper } from "<?= base_url('assets/js/main.js') ?>";
 *   setTimeout(function() {
 *     swalWrapper().success("t('-'t");
 *   }, 1000);
 */
export default class SwalWrapper {
  /**
   * Creates an instance of SwalWrapper.
   *
   * The constructor sets up the default options which include:
   * - Theme (dark/light based on global window.hyper_isDarkMode)
   * - Custom button colors matching Bulma CSS variables.
   * - No backdrop effect for a less intrusive style.
   */
  constructor() {
    this.defaultOptions = {
      theme: window.hyper_isDarkMode ? "dark" : "light",
      confirmButtonColor: "var(--bulma-primary)",
      cancelButtonColor: "var(--bulma-danger)",
      backdrop: false,
    };
  }

  /**
   * Gets the underlying Swal (SweetAlert2) library.
   *
   * @returns {object} - The imported Swal instance.
   */
  get() {
    return Swal;
  }

  /**
   * Displays a base alert using SweetAlert2 with merged options.
   *
   * This method merges the default options with any custom options
   * provided.
   *
   * @param {object} customOptions - Options to override the defaults.
   * @returns {Promise<object>} - A promise that resolves when the alert is closed.
   */
  async fire(customOptions) {
    const options = { ...this.defaultOptions, ...customOptions };
    return Swal.fire(options);
  }

  /**
   * Displays a confirmation dialog.
   *
   * Uses i18next to localize the title and text.
   *
   * @param {object} [options={}] - Additional options for the confirmation dialog.
   * @returns {Promise<object>} - A promise that resolves with the user's response.
   */
  confirm(options = {}) {
    return this.fire({
      title: i18next.t("areYouSure"),
      text: i18next.t("youWillNotBeAbleToRevertThis"),
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "var(--bulma-danger)",
      cancelButtonColor: "var(--bulma-info)",
      confirmButtonText: i18next.t("confirm"),
      cancelButtonText: i18next.t("cancel"),
      ...options,
    });
  }

  /**
   * Displays a toast notification.
   *
   * The toast pops up in the bottom-left corner and automatically dismisses after 3 seconds.
   *
   * @param {object} [options={}] - Additional options for the toast notification.
   * @returns {Promise<object>} - A promise that resolves when the toast is closed.
   */
  toast(options = {}) {
    return this.fire({
      position: "bottom-start",
      toast: true,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener("mouseenter", Swal.stopTimer);
        toast.addEventListener("mouseleave", Swal.resumeTimer);
      },
      ...options,
    });
  }

  /**
   * Displays a success toast notification.
   *
   * @param {string} message - The success message to display.
   * @param {object} [options={}] - Additional options for the toast.
   * @returns {Promise<object>} - A promise that resolves when the toast is closed.
   */
  success(message, options = {}) {
    return this.toast({
      icon: "success",
      title: message,
      ...options,
    });
  }

  /**
   * Displays a modal success alert (non-toast).
   *
   * This alert automatically dismisses after 1.5 seconds.
   *
   * @param {string} message - The success message to display.
   * @param {object} [options={}] - Additional options for the alert.
   * @returns {Promise<object>} - A promise that resolves when the alert is closed.
   */
  successAlert(message, options = {}) {
    return this.fire({
      icon: "success",
      title: message,
      showConfirmButton: false,
      timer: 1500,
      ...options,
    });
  }

  /**
   * Displays an error toast notification.
   *
   * @param {string} message - The error message to display.
   * @param {object} [options={}] - Additional options for the toast.
   * @returns {Promise<object>} - A promise that resolves when the toast is closed.
   */
  error(message, options = {}) {
    return this.toast({
      icon: "error",
      title: message,
      ...options,
    });
  }

  /**
   * Displays a modal error alert.
   *
   * @param {string} message - The error message to display.
   * @param {object} [options={}] - Additional options for the alert.
   * @returns {Promise<object>} - A promise that resolves when the alert is closed.
   */
  errorAlert(message, options = {}) {
    return this.fire({
      icon: "error",
      title: message,
      ...options,
    });
  }

  /**
   * Displays a prompt dialog with a text input.
   *
   * The prompt includes a loader on confirmation and prevents outside clicks while loading.
   *
   * @param {object} [options={}] - Additional options for the prompt.
   * @returns {Promise<object>} - A promise that resolves with the user's input.
   */
  prompt(options = {}) {
    return this.fire({
      input: "text",
      inputAttributes: { autocapitalize: "off" },
      showCancelButton: true,
      showLoaderOnConfirm: true,
      allowOutsideClick: () => !Swal.isLoading(),
      ...options,
    });
  }
}

// For traditional modules usage (bindings provided above allow both ES modules
// and global script usage, as the following code would attach it to the window):
//
// if (typeof window !== "undefined") {
//   window.hyper_swal = () => new SwalWrapper();
// }
