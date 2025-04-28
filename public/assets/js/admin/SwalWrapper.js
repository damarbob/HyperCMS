import Swal from "https://cdn.jsdelivr.net/npm/sweetalert2@11.19.1/+esm";
import i18next from "https://deno.land/x/i18next/index.js";

export default class SwalWrapper {
  constructor() {
    this.defaultOptions = {
      theme: window.hyper_isDarkMode ? "dark" : "light",
      confirmButtonColor: "var(--bulma-primary)",
      cancelButtonColor: "var(--bulma-danger)",
      backdrop: false,
    };
  }

  get() {
    return Swal;
  }

  // Base alert method
  async fire(customOptions) {
    const options = { ...this.defaultOptions, ...customOptions };
    return Swal.fire(options);
  }

  // Confirm dialog
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

  // Toast notification
  toast(options = {}) {
    return this.fire({
      position: "top-end",
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

  // Success methods
  success(message, options = {}) {
    return this.toast({
      icon: "success",
      title: message,
      ...options,
    });
  }

  successAlert(message, options = {}) {
    return this.fire({
      icon: "success",
      title: message,
      showConfirmButton: false,
      timer: 1500,
      ...options,
    });
  }

  // Error methods
  error(message, options = {}) {
    return this.toast({
      icon: "error",
      title: message,
      ...options,
    });
  }

  errorAlert(message, options = {}) {
    return this.fire({
      icon: "error",
      title: message,
      ...options,
    });
  }

  // Prompt method
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
