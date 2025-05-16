<?php
// Get the current request instance
$request = service('request');

// Get the URI string
$currentRoute = $request->getUri()->getPath();
?>

<?= $this->extend('admin/layout/page') ?>

<?= $this->section('head') ?>

<!-- Datatables -->
<link href="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.css" rel="stylesheet" integrity="sha384-wAbr9qEp5JojSKDr01s3gfk2usG6WR/OfpUIFEliYPzIBy5Jr9WBChdyqfWfbtt6" crossorigin="anonymous">

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/v/bm/jq-3.7.0/jszip-3.10.1/dt-2.2.2/b-3.2.2/b-colvis-3.2.2/b-html5-3.2.2/b-print-3.2.2/cr-2.0.4/fh-4.0.1/r-3.0.4/sl-3.0.0/datatables.min.js" integrity="sha384-JYvoIYf/4ra9ifw1ESGWSNm3QVSdAuT8OaSDJLTKTkRWntshpsM1beOZKdjAXOAb" crossorigin="anonymous"></script>
<!-- End of datatables -->

<script>
    function confirmSelectedFiles() {
        const selectedFiles = window.hyper.factory.fileManager.getSelectedFiles();
        if (true) {
            // Post the message with the deserialized data included
            window.parent.postMessage({
                action: 'filesSelected_r<?= $requesterId ?>',
                data: selectedFiles
            }, '<?= base_url() ?>');

            // Post TinyMCE action
            window.parent.postMessage({
                mceAction: 'filesSelected_r<?= $requesterId ?>', // Important for TinyMCE to read
                data: selectedFiles
            }, '<?= base_url() ?>');
        }
    }
</script>

<!-- Dropzone -->
<script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css">

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Codicon for Monaco Editor -->
<link href="https://cdn.jsdelivr.net/npm/vscode-codicons@0.0.17/dist/codicon.min.css" rel="stylesheet">

<style>
    .loader {
        width: 1rem;
        height: 1rem;
        border: 3px solid var(--bulma-primary);
        border-bottom-color: transparent;
        border-radius: 50%;
        display: inline-block;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .loader-body {
        animation: rotate 1s infinite;
        height: 50px;
        width: 50px;
    }

    .loader-body:before,
    .loader-body:after {
        border-radius: 50%;
        content: "";
        display: block;
        height: 20px;
        width: 20px;
    }

    .loader-body:before {
        animation: ball1 1s infinite;
        background-color: var(--bulma-link);
        box-shadow: 30px 0 0 var(--bulma-primary)0;
        margin-bottom: 10px;
    }

    .loader-body:after {
        animation: ball2 1s infinite;
        background-color: var(--bulma-primary);
        box-shadow: 30px 0 0 var(--bulma-scheme-main);
    }

    @keyframes rotate {
        0% {
            transform: rotate(0deg) scale(0.8)
        }

        50% {
            transform: rotate(360deg) scale(1.2)
        }

        100% {
            transform: rotate(720deg) scale(0.8)
        }
    }

    @keyframes ball1 {
        0% {
            box-shadow: 30px 0 0 var(--bulma-primary);
        }

        50% {
            box-shadow: 0 0 0 var(--bulma-primary);
            margin-bottom: 0;
            transform: translate(15px, 15px);
        }

        100% {
            box-shadow: 30px 0 0 var(--bulma-primary);
            margin-bottom: 10px;
        }
    }

    @keyframes ball2 {
        0% {
            box-shadow: 30px 0 0 var(--bulma-warning);
        }

        50% {
            box-shadow: 0 0 0 var(--bulma-warning);
            margin-top: -20px;
            transform: translate(15px, 15px);
        }

        100% {
            box-shadow: 30px 0 0 var(--bulma-warning);
            margin-top: 0;
        }
    }

    .loader-overlay {
        display: flex;
        justify-content: center;
        align-items: center;
        position: fixed;
        background-color: hsla(var(--bulma-scheme-h), var(--bulma-scheme-s), var(--bulma-scheme-main-l), 0.5);
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000000000;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div>
    <!-- Loader -->
    <div id="loaderBody" class="loader-overlay">
        <span class="loader-body"></span>
        <span class="is-sr-only"><?= lang('Admin.loading') ?></span>
    </div>
    <!-- Toolbar -->
    <div class="pb-3" style="background-color: var(--bulma-scheme-main);">
        <div class="field is-grouped is-grouped-multiline">

            <!-- Iframe specific -->
            <div class="control is-in-iframe">
                <button class="button is-primary" onclick="confirmSelectedFiles()">
                    <span class="icon">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <span>
                        <?= lang('Admin.select') ?>
                    </span>
                </button>
            </div>

            <!-- Upload Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button is-primary" onclick="toggleDropzone()">
                        <span class="icon">
                            <i class="fas fa-upload"></i>
                        </span>
                        <span><?= lang('Admin.upload') ?></span>
                    </button>
                    <button class="button is-primary" onclick="window.hyper.factory.fileManager.refreshFileList()" data-tippy-content="<?= lang('Admin.refresh') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-arrows-rotate"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Create Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="window.hyper.factory.fileManager.createFile()" data-tippy-content="<?= lang('Admin.createNewFile') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-file-circle-plus"></i>
                        </span>
                    </button>
                    <button class="button" onclick="window.hyper.factory.fileManager.createFolder()" data-tippy-content="<?= lang('Admin.createNewFolder') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-folder-plus"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Copy/Move Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="window.hyper.factory.fileManager.copySelectedFiles()" data-tippy-content="<?= lang('Admin.copySelected') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-copy"></i>
                        </span>
                    </button>
                    <button class="button is-warning" onclick="window.hyper.factory.fileManager.moveSelectedFiles()" data-tippy-content="<?= lang('Admin.moveSelected') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-scissors"></i>
                        </span>
                    </button>
                    <button class="button" onclick="window.hyper.factory.fileManager.pasteFiles()" data-tippy-content="<?= lang('Admin.paste') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-paste"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Archive Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="window.hyper.factory.fileManager.extractSelectedFiles()" data-tippy-content="<?= lang('Admin.extractZipFile') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-box-open"></i>
                        </span>
                    </button>
                    <button class="button" onclick="window.hyper.factory.fileManager.compressSelectedFiles()" data-tippy-content="<?= lang('Admin.compressToZip') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-file-zipper"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Delete Group -->
            <div class="control">
                <button class="button is-danger" onclick="window.hyper.factory.fileManager.deleteSelectedFiles()" data-tippy-content="<?= lang('Admin.deleteSelected') ?>" data-tippy-placement="bottom">
                    <span class="icon">
                        <i class="fas fa-trash"></i>
                    </span>
                </button>
            </div>
        </div>

        <!-- Dropzone Container -->
        <div id="dropzoneContainer" class="box mt-3" style="display: none;">
            <form action="<?= base_url('admin/api/file-manager/upload') ?>" class="dropzone" id="fileDropzone" style="border: none; background: none;"></form>
            <p id="uploadProgress" style="display: none;"><?= lang('Admin.uploadingFile') ?></p>
        </div>
    </div>

    <!-- File Table -->
    <table id="hyperTable" class="table is-hoverable is-fullwidth">
        <thead>
            <tr>
                <th>
                    <span>
                        <i class="fa-regular fa-bookmark"></i>
                    </span>
                </th>
                <th><?= lang('Admin.name') ?></th>
                <th><?= lang('Admin.size') ?></th>
                <th><?= lang('Admin.permission') ?></th>
                <th><?= lang('Admin.dateModified') ?></th>
                <th><?= lang('Admin.action') ?></th>
            </tr>
        </thead>
        <tbody id="fileList">
            <!-- Dynamic File List here -->
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal" id="viewModal">
    <div class="modal-background"></div>
    <div class="modal-card is-large">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span id="viewModalLabel"><?= lang('Admin.title') ?></span>
                <span id="loaderModal" class="loader is-small ml-2 mr-2" style="display: none;"></span>
            </p>
            <button class="delete" aria-label="close" data-dismiss="modal"></button>
        </header>
        <section class="modal-card-body">
            <div id="viewModalKonten">...</div>
            <div id="monaco" class="is-hidden" style="height: 512px;"></div>
            <textarea id="fileEditor" class="textarea is-hidden" rows="10"></textarea>
        </section>
        <footer class="modal-card-foot is-justify-content-space-between">
            <button class="button is-light" data-dismiss="modal">
                <span class="icon">
                    <i class="fas fa-chevron-left"></i>
                </span>
                <span><?= lang('Admin.cancel') ?></span>
            </button>
            <button id="saveButton" class="button is-primary" style="display: none;">
                <span class="icon">
                    <i class="fas fa-save"></i>
                </span>
                <span><?= lang('Admin.save') ?></span>
            </button>
        </footer>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<script>
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
    const dropzoneContainer = document.getElementById('dropzoneContainer');
    const uploadProgress = document.getElementById('uploadProgress');
    let isToggledByButton = false; // Flag to track manual dropzone toggle

    /**
     * Toggle the visibility of the dropzone container.
     * This function is called when the user clicks the button to
     * manually display or hide the dropzone.
     */
    function toggleDropzone() {
        isToggledByButton = !isToggledByButton;
        dropzoneContainer.style.display = isToggledByButton ? 'block' : 'none';
    }

    /**
     * Automatically display the dropzone when a user drags files over the page.
     * The dropzone stays visible until the current upload operation completes.
     */
    document.addEventListener('dragenter', (event) => {
        if (event.dataTransfer.types.includes('Files')) {
            dropzoneContainer.style.display = 'block';
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
        init: function() {
            const dropzone = this;

            // When a file is added, display the progress bar.
            this.on("addedfile", function() {
                uploadProgress.style.display = 'block';
            });

            // When all files in the queue have been processed:
            this.on("queuecomplete", function() {
                uploadProgress.style.display = 'none';

                // If the dropzone was not manually toggled, hide the container.
                if (!isToggledByButton) {
                    dropzoneContainer.style.display = 'none';
                }

                // Refresh the file list using the global file manager.
                window.hyper.factory.fileManager.listFiles(window.hyper.factory.fileManager.currentPath);
            });
        },
        /**
         * Set CSRF tokens in the headers to secure the upload request.
         * CSRF tokens are injected by server-side PHP functions.
         */
        headers: {
            '<?= csrf_header() ?>': '<?= csrf_hash() ?>',
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
        params: function(files, xhr, chunk) {
            return {
                path: window.hyper.factory.fileManager.currentPath
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
                for (let node of file.previewElement.querySelectorAll("[data-dz-errormessage]")) {
                    node.textContent = error;
                }
                // Display an error toast using SweetAlert.
                window.hyper.factory.swal.error(error, {
                    showConfirmButton: true,
                    timer: false
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
        success: function(file, response) {
            window.hyper.factory.swal.success("<?= lang('Admin.fileSuccessfullyUploaded') ?>");
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // -------------------------------------------------------------------
        // Initialize the File Manager File Listing
        // -------------------------------------------------------------------
        // Loads the initial list of files (using default path)
        window.hyper.factory.fileManager.listFiles();

        // -------------------------------------------------------------------
        // Initialize the DataTable for the File Manager
        // -------------------------------------------------------------------
        window.hyper_fileManager_table = new DataTable('#hyperTable', {
            order: [], // Disable any initial column ordering
            columnDefs: [{
                    // Column 0: Checkbox column
                    // Force a narrow fixed width, disable ordering and hide from colvis options
                    targets: [0],
                    width: "2rem",
                    orderable: false,
                    className: 'noVis' // Mark to exclude from Column Visibility controls
                },
                {
                    // Column 1: Main file column
                    // Always visible in responsive mode (locked)
                    targets: [1],
                    className: 'all'
                },
                {
                    // Column 3: Size and permissions data column
                    // Hide this column in the default view
                    targets: [3],
                    visible: false
                },
                {
                    // Column 4: Date modified column
                    // Indicates date data so the type is set to 'date'
                    targets: [4],
                    type: 'date'
                }
            ],
            // -------------------------------------------------------------------
            // Define the Table Layout and UI Controls
            // -------------------------------------------------------------------
            layout: {
                topStart: {
                    buttons: [{
                            extend: "colvis", // Column visibility button
                            text: '<i class="fa-solid fa-table mr-2"></i><?= lang('Admin.data') ?>',
                            columns: ':not(.noVis)' // Exclude columns marked with 'noVis'
                        },
                        {
                            extend: "excelHtml5", // Export to Excel button
                            text: '<i class="fa-solid fa-download mr-2"></i><?= lang('Admin.excel') ?>'
                        },
                        {
                            extend: "print", // Print button
                            text: '<i class="fa-solid fa-print mr-2"></i><?= lang('Admin.print') ?>'
                        }
                    ]
                },
                topEnd: {
                    pageLength: {
                        menu: [10, 25, 50, 100] // Page-length options
                    },
                    search: {
                        placeholder: "<?= lang('Admin.searchWithinFolder') ?>",
                        text: "_INPUT_"
                    }
                },
                bottomEnd: {
                    paging: {
                        numbers: true // Enable numeric pagination controls
                    }
                }
            },
            pageLength: 100, // Default number of rows per page
            select: true, // Enable row selection (requires DataTables Select extension)
            colReorder: true, // Allow column reordering by the user
            fixedHeader: true, // Keep the header fixed during scrolling
            responsive: true // Enable responsive behavior for different devices
        });

        // -------------------------------------------------------------------
        // Attach Delegated Event for Row Double-Click
        // -------------------------------------------------------------------
        // This event handler uses delegation on the table element so it will
        // work correctly even after DataTables redraws table rows.
        $('#hyperTable').on('dblclick', 'tr', function() {
            // Get the "file-link" element within the row
            const item = this.querySelector('.file-link');
            if (item) {
                // Retrieve the file path and type (folder or file)
                const path = item.getAttribute('data-path');
                const type = item.getAttribute('data-type');
                // If the item is a folder, navigate into it
                if (type === 'folder') {
                    window.hyper.factory.fileManager.listFiles(path);
                }
                // If the item is a file, view it
                else if (type === 'file') {
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
            onSave: function(editor) {
                // Save the currently open file when the editor triggers a save
                window.hyper.factory.fileManager.saveFile(window.hyper.factory.fileManager.currentFile);
            },
            language: "javascript" // Set the default language mode
        });
    });
</script>
<?= $this->endSection() ?>