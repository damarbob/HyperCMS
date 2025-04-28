import "./admin/translations/I18n.js";
import SwalWrapper from "./admin/SwalWrapper.js";

// Define the function
function swalWrapper() {
  return new SwalWrapper();
}

// For ES modules
export { swalWrapper };

// For traditional script usage
if (typeof window !== "undefined") {
  window.hyper_swal = swalWrapper();
}

// <!-- Example usage: Accessing swalWrapper from module and traditional script -->
// <script type="text/javascript">
//     document.addEventListener('DOMContentLoaded', function() {
//         window.hyper_swal.success("'-')");
//     });
// </script>
// <script type="module">
//     import {
//         swalWrapper
//     } from "<?= base_url('assets/js/main.js') ?>"

//     setTimeout(function() {
//         swalWrapper().success("t('-'t");
//     }, 1000);
// </script>
