var hyper = window.hyper;

function confirmSelectedFiles() {
  const selectedFiles = hyper.factory.fileManager.getSelectedFiles();
  if (true) {
    // Post the message with the deserialized data included
    window.parent.postMessage(
      {
        action: `filesSelected_r${hyper.data.requesterId}`,
        data: selectedFiles,
      },
      hyper.config.baseUrl
    );

    // Post TinyMCE action
    window.parent.postMessage(
      {
        mceAction: `filesSelected_r${hyper.data.requesterId}`, // Important for TinyMCE to read
        data: selectedFiles,
      },
      hyper.config.baseUrl
    );
  }
}

function confirmCurrentFile() {
  const currentFile = hyper.factory.fileManager.currentFile;

  // Post the message with the deserialized data included
  window.parent.postMessage(
    {
      action: `filesSelected_r${hyper.data.requesterId}`,
      data: [currentFile],
    },
    hyper.config.baseUrl
  );

  // Post TinyMCE action
  window.parent.postMessage(
    {
      mceAction: `filesSelected_r${hyper.data.requesterId}`, // Important for TinyMCE to read
      data: [currentFile],
    },
    hyper.config.baseUrl
  );
}

/**
 * Dropzone File Upload Handler & UI Management
 *
 * This script handles the visibility, progress, and callbacks for
 * file uploads using Dropzone.js. It supports:
 *   - Toggling the dropzone manually via a button.
 *   - Automatically showing the dropzone when files are dragged over.
 *   - Displaying an upload progress indicator.
 *   - Handling upload success and error events.
 *
 * Dependencies:
 *   - Dropzone.js (with automatic discovery disabled)
 *   - A global hyper.factory.fileManager object (for managing file lists)
 *   - A global hyper.factory.swal object (for displaying alerts)
 *   - Server-side CSRF auth tokens injected via PHP functions.
 */

// Get the DOM elements for the dropzone container and upload progress display.
const dropzoneContainer = document.getElementById("dropzoneContainer");
const uploadProgress = document.getElementById("uploadProgress");
let isToggledByButton = false; // Flag to track manual dropzone toggle

/**
 * Toggle the visibility of the dropzone container.
 * This function is called when the user clicks the button to
 * manually display or hide the dropzone.
 */
function toggleDropzone() {
  isToggledByButton = !isToggledByButton;
  dropzoneContainer.style.display = isToggledByButton ? "block" : "none";
}

/**
 * Automatically display the dropzone when a user drags files over the page.
 * The dropzone stays visible until the current upload operation completes.
 */
document.addEventListener("dragenter", (event) => {
  if (event.dataTransfer.types.includes("Files")) {
    dropzoneContainer.style.display = "block";
  }
});

/**
 * Configure Dropzone for file uploads.
 *
 * The following configuration sets up:
 *   - Initialization event callbacks.
 *   - Custom headers with CSRF tokens.
 *   - Additional parameters (like the currently viewed path).
 *   - Error handling.
 */
Dropzone.options.fileDropzone = {
  /**
   * init() is executed when the Dropzone instance is created.
   * Here we:
   *   - Show a progress indicator when a file is added.
   *   - Hide the progress indicator when the upload queue is complete.
   *   - Optionally hide the dropzone if it wasn't toggled open.
   *   - Refresh the file list after upload.
   */
  init: function () {
    const dropzone = this;

    // When a file is added, display the progress bar.
    this.on("addedfile", function () {
      uploadProgress.style.display = "block";
    });

    // When all files in the queue have been processed:
    this.on("queuecomplete", function () {
      uploadProgress.style.display = "none";

      // If the dropzone was not manually toggled, hide the container.
      if (!isToggledByButton) {
        dropzoneContainer.style.display = "none";
      }

      // Refresh the file list using the global file manager.
      window.hyper.factory.fileManager.listFiles(
        window.hyper.factory.fileManager.currentPath
      );
    });
  },
  /**
   * Set CSRF tokens in the headers to secure the upload request.
   * CSRF tokens are injected by server-side PHP functions.
   */
  headers: {
    [hyper.config.csrfHeader]: hyper.config.csrfHash,
  },
  /**
   * Append additional parameters to the upload request.
   * Here, we pass the current file path from the global file manager.
   *
   * @param {Array} files - Files being uploaded (Dropzone default)
   * @param {XMLHttpRequest} xhr - The underlying XHR instance
   * @param {Object} chunk - Chunk upload data (if applicable)
   * @return {Object} Parameters to add to the upload request.
   */
  params: function (files, xhr, chunk) {
    return {
      path: window.hyper.factory.fileManager.currentPath,
    };
  },
  /**
   * Error callback to handle file upload errors.
   * It updates the preview element with an error message, and
   * displays a SweetAlert toast with the error information.
   *
   * @param {Object} file - The file that encountered an error.
   * @param {(string|Object)} error - The error message or error object.
   */
  error(file, error) {
    if (file.previewElement) {
      file.previewElement.classList.add("dz-error");
      // Normalize error message: use message attribute if available.
      if (typeof error !== "string" && (error.message || error.error)) {
        error = error.message || error.error;
      }
      // Update all error message nodes within the file preview.
      for (let node of file.previewElement.querySelectorAll(
        "[data-dz-errormessage]"
      )) {
        node.textContent = error;
      }
      // Display an error toast using SweetAlert.
      window.hyper.factory.swal.error(error, {
        showConfirmButton: true,
        timer: false,
      });
    }
  },
};

// Disable Dropzone's auto-discovery so we can initialize it manually.
Dropzone.autoDiscover = false;

// Create a new Dropzone instance on the element with ID "fileDropzone".
// Additional options like maximum file size are specified here.
const fileDropzone = new Dropzone("#fileDropzone", {
  maxFilesize: 2, // Maximum file size in MB.
  /**
   * Callback executed upon a successful upload.
   * It displays a SweetAlert success message.
   *
   * @param {Object} file - The successfully uploaded file.
   * @param {Object} response - The server response.
   */
  success: function (file, response) {
    window.hyper.factory.swal.success(
      hyper.lang.Admin.fileSuccessfullyUploaded
    );
  },
});

document.addEventListener("DOMContentLoaded", () => {
  // -------------------------------------------------------------------
  // Initialize the File Manager File Listing
  // -------------------------------------------------------------------
  // Loads the initial list of files (using default path)
  window.hyper.factory.fileManager.listFiles();

  // -------------------------------------------------------------------
  // Initialize the DataTable for the File Manager
  // -------------------------------------------------------------------
  window.hyper_fileManager_table = new DataTable("#hyperTable", {
    order: [], // Disable any initial column ordering
    columnDefs: [
      {
        // Column 0: Checkbox column
        // Force a narrow fixed width, disable ordering and hide from colvis options
        targets: [0],
        width: "2rem",
        orderable: false,
        className: "noVis", // Mark to exclude from Column Visibility controls
      },
      {
        // Column 1: Main file column
        // Always visible in responsive mode (locked)
        targets: [1],
        className: "all",
      },
      {
        // Column 3: Size and permissions data column
        // Hide this column in the default view
        targets: [3],
        visible: false,
      },
      {
        // Column 4: Date modified column
        // Indicates date data so the type is set to 'date'
        targets: [4],
        type: "date",
      },
    ],
    // -------------------------------------------------------------------
    // Define the Table Layout and UI Controls
    // -------------------------------------------------------------------
    layout: {
      topStart: {
        buttons: [
          {
            extend: "colvis", // Column visibility button
            text: `<i class="fa-solid fa-table mr-2"></i>${hyper.lang.Admin.data}`,
            columns: ":not(.noVis)", // Exclude columns marked with 'noVis'
          },
          {
            extend: "excelHtml5", // Export to Excel button
            text: `<i class="fa-solid fa-download mr-2"></i>${hyper.lang.Admin.excel}`,
          },
          {
            extend: "print", // Print button
            text: `<i class="fa-solid fa-print mr-2"></i>${hyper.lang.Admin.print}`,
          },
        ],
      },
      topEnd: {
        pageLength: {
          menu: [10, 25, 50, 100], // Page-length options
        },
        search: {
          placeholder: hyper.lang.Admin.searchWithinFolder,
          text: "_INPUT_",
        },
      },
      bottomEnd: {
        paging: {
          numbers: true, // Enable numeric pagination controls
        },
      },
    },
    pageLength: 100, // Default number of rows per page
    select: true, // Enable row selection (requires DataTables Select extension)
    colReorder: true, // Allow column reordering by the user
    fixedHeader: true, // Keep the header fixed during scrolling
    responsive: true, // Enable responsive behavior for different devices
  });

  // -------------------------------------------------------------------
  // Attach Delegated Event for Row Double-Click
  // -------------------------------------------------------------------
  // This event handler uses delegation on the table element so it will
  // work correctly even after DataTables redraws table rows.
  $("#hyperTable").on("dblclick", "tr", function () {
    // Get the "file-link" element within the row
    const item = this.querySelector(".file-link");
    if (item) {
      // Retrieve the file path and type (folder or file)
      const path = item.getAttribute("data-path");
      const type = item.getAttribute("data-type");
      // If the item is a folder, navigate into it
      if (type === "folder") {
        window.hyper.factory.fileManager.listFiles(path);
      }
      // If the item is a file, view it
      else if (type === "file") {
        window.hyper.factory.fileManager.viewFile(path);
      }
    }
  });

  // -------------------------------------------------------------------
  // Initialize the Monaco Editor for File Editing
  // -------------------------------------------------------------------
  // This initializes the Monaco editor with the provided configuration.
  // The onSave callback triggers a file save operation from the file manager.
  window.hyper_fileManagerMonaco = window.hyper.factory.monaco({
    editorContainerId: "monaco", // The container element ID for Monaco
    textareaId: "fileEditor", // The ID of the textarea linked to the editor
    onSave: function (editor) {
      // Save the currently open file when the editor triggers a save
      window.hyper.factory.fileManager.saveFile(
        window.hyper.factory.fileManager.currentFile
      );
    },
    language: "javascript", // Set the default language mode
  });
});
