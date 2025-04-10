<?php
// Get the current request instance
$request = service('request');

// Get the URI string
$currentRoute = $request->getUri()->getPath();
?>

<?= $this->extend('admin/layout/page') ?>

<?= $this->section('head') ?>
<script>
    function confirmSelectedFiles() {
        const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
            .map(checkbox => checkbox.getAttribute('data-path'));
        if (true) {
            // Post the message with the deserialized data included
            window.parent.postMessage({
                mceAction: 'filesSelected',
                data: selectedFiles
            }, '*'); // @TODO: Use proper target origin
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
        <span class="is-sr-only">Loading...</span>
    </div>
    <!-- Toolbar -->
    <div class="pb-3" style="background-color: var(--bulma-scheme-main);">
        <div class="field is-grouped is-grouped-multiline">

            <!-- Iframe specific -->
            <div class="control is-in-iframe is-hidden">
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
                    <button class="button is-primary" onclick="refreshFileList()" data-tippy-content="<?= lang('Admin.refresh') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-arrows-rotate"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Create Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="createFile()" data-tippy-content="<?= lang('Admin.createNewFile') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-file-circle-plus"></i>
                        </span>
                    </button>
                    <button class="button" onclick="createFolder()" data-tippy-content="<?= lang('Admin.createNewFolder') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-folder-plus"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Copy/Move Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="copySelectedFiles()" data-tippy-content="<?= lang('Admin.copySelected') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-copy"></i>
                        </span>
                    </button>
                    <button class="button is-warning" onclick="moveSelectedFiles()" data-tippy-content="<?= lang('Admin.moveSelected') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-scissors"></i>
                        </span>
                    </button>
                    <button class="button" onclick="pasteFiles()" data-tippy-content="<?= lang('Admin.paste') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-paste"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Archive Group -->
            <div class="control">
                <div class="buttons has-addons">
                    <button class="button" onclick="extractSelectedFiles()" data-tippy-content="<?= lang('Admin.extractZipFile') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-box-open"></i>
                        </span>
                    </button>
                    <button class="button" onclick="compressSelectedFiles()" data-tippy-content="<?= lang('Admin.compressToZip') ?>" data-tippy-placement="bottom">
                        <span class="icon">
                            <i class="fas fa-file-zipper"></i>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Delete Group -->
            <div class="control">
                <button class="button is-danger" onclick="deleteSelectedFiles()" data-tippy-content="<?= lang('Admin.deleteSelected') ?>" data-tippy-placement="bottom">
                    <span class="icon">
                        <i class="fas fa-trash"></i>
                    </span>
                </button>
            </div>
        </div>

        <!-- Dropzone Container -->
        <div id="dropzoneContainer" class="box mt-3" style="display: none;">
            <form action="<?= base_url('api/file-manager/upload') ?>" class="dropzone" id="fileDropzone" style="border: none; background: none;"></form>
            <p id="uploadProgress" style="display: none;"><?= lang('Admin.uploadingFile') ?></p>
        </div>
    </div>

    <!-- File Table -->
    <div class="table-container">
        <table class="table is-hoverable is-fullwidth">
            <thead>
                <tr>
                    <th>
                        <label class="checkbox">
                            <input id="selectAll" type="checkbox">
                        </label>
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

<?= $this->section('scripts') ?>
<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', () => {
        // Adjust UI
        const inIframe = window.self !== window.top; // Check if loaded inside an iframe

        // Hide elements that are iframe-specific
        if (!inIframe) {
            document.querySelectorAll('.is-in-iframe').forEach(element => {
                element.classList.add('is-hidden');
            })
        }
        else {
            document.querySelectorAll('.is-in-iframe').forEach(element => {
                element.classList.remove('is-hidden');
            })
        }
        // Initialize tooltips
        tippy('[data-tippy-content]');
    });
</script>
<script>
    window.currentPath = ''; // Keep track of the current path
    window.currentFile = ''; // Keep track of the current file

    const dropzoneContainer = document.getElementById('dropzoneContainer');
    const uploadProgress = document.getElementById('uploadProgress');
    let isToggledByButton = false;

    // Toggle Dropzone visibility on button click
    function toggleDropzone() {
        isToggledByButton = !isToggledByButton;
        dropzoneContainer.style.display = isToggledByButton ? 'block' : 'none';
    }

    // Show Dropzone when dragging files, but do not hide until upload finishes
    document.addEventListener('dragenter', (event) => {
        if (event.dataTransfer.types.includes('Files')) {
            dropzoneContainer.style.display = 'block';
        }
    });

    // Dropzone configuration with upload progress and visibility control
    Dropzone.options.fileDropzone = {
        init: function() {
            const dropzone = this;
            // Show upload progress
            this.on("addedfile", function() {
                uploadProgress.style.display = 'block';
            });
            // Track number of files in the upload queue
            this.on("queuecomplete", function() {
                uploadProgress.style.display = 'none';

                if (!isToggledByButton) {
                    dropzoneContainer.style.display = 'none';
                }

                listFiles(window.currentPath); // Refresh the file list after uploading
            });
        },
        headers: {
            '<?= csrf_header() ?>': '<?= csrf_hash() ?>',
        },
        params: function(files, xhr, chunk) {
            // Append the path as a parameter
            return {
                path: window.currentPath
            };
        },
        error(file, error) {
            if (file.previewElement) {
                file.previewElement.classList.add("dz-error");
                if (typeof error !== "string" && (error.message || error.error)) {
                    // Use 'message' attribute to match CI4 error messages and fallback to 'error' attribute
                    error = error.message || error.error;
                }
                for (let node of file.previewElement.querySelectorAll(
                        "[data-dz-errormessage]"
                    )) {
                    node.textContent = error;
                }
            }
        },
    };

    Dropzone.autoDiscover = false;
    const fileDropzone = new Dropzone("#fileDropzone", {
        maxFilesize: 2,
        success: function(file, response) {
            // alert("File uploaded successfully.");
            // listFiles();
        }
    });

    let clipboard = {
        files: [],
        action: ''
    };

    function downloadFile(path) {
        window.location.href = '<?= base_url('api/file-manager/download') ?>/' + encodeURIComponent(hexEncode(path));
    }

    function addToClipboard(path, action) {
        clipboard = {
            files: [path],
            action
        };

        console.log("Setting clipboard:", clipboard); // Debug log

        fetch('<?= base_url('api/file-manager/set-clipboard') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(clipboard)
            }).then(response => response.json())
            .then(data => {
                if (data.status) {
                    // Show success toast 
                    Swal.fire({
                        icon: "success",
                        toast: true,
                        title: `${action.charAt(0).toUpperCase() + action.slice(1)}: <?= lang('Admin.copiedSuccessfullyReadyToPaste') ?>`,
                        showConfirmButton: false,
                        timer: 1500,
                    });
                } else {
                    // Show error toast 
                    Swal.fire({
                        icon: "error",
                        toast: true,
                        title: "<?= lang('Admin.failedToCopy') ?>: " + data.error,
                        confirmButtonText: "<?= lang('Admin.close') ?>"
                    });
                }
            })
            .catch(error => console.error("Error setting clipboard:", error));
    }

    function pasteFiles() {
        fetch('<?= base_url('api/file-manager/paste') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    destination: window.currentPath
                }) // Set the destination path
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    // Show success toast 
                    Swal.fire({
                        icon: "success",
                        toast: true,
                        title: `<?= lang('Admin.pastedSuccessfully') ?>`,
                        showConfirmButton: false,
                        timer: 1500,
                    });
                    listFiles(window.currentPath); // Refresh the file list
                } else if (data.error) {
                    // Show error toast 
                    Swal.fire({
                        icon: "error",
                        toast: true,
                        title: "<?= lang('Admin.failedToPaste') ?>: " + data.error,
                        confirmButtonText: "<?= lang('Admin.close') ?>"
                    });
                }
            })
            .catch(error => console.error("Error pasting files:", error));
    }

    document.addEventListener('DOMContentLoaded', function() {

        listFiles();

        document.getElementById('selectAll').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        // Delegate click events to dynamically created buttons within #fileList
        // refreshActionButtonListener();

    });

    function refreshActionButtonListener() {
        document.getElementById('fileList').addEventListener('click', function(event) {
            if (event.target.tagName === 'BUTTON') {
                const action = event.target.getAttribute('data-action');
                const path = event.target.getAttribute('data-path');

                if (action === 'open') {
                    listFiles(path);
                } else if (action === 'view') {
                    viewFile(path);
                } else if (action === 'download') {
                    downloadFile(path);
                } else if (action === 'copy') {
                    addToClipboard(path, 'copy');
                } else if (action === 'move') {
                    addToClipboard(path, 'move');
                } else if (action === 'back') {
                    listFiles(path); // Navigate up one level
                } else if (action === 'rename') {
                    renameFile(path);
                }
            }
        });
    }

    function viewFile(path) {
        // console.log(path);

        window.currentFile = path;

        openModal(document.getElementById('viewModal')); // Open view modal

        if (!document.getElementById('monaco').classList.contains("is-hidden")) {
            document.getElementById('monaco').classList.add('is-hidden');
        }

        document.getElementById('saveButton').onclick = function() {
            saveFile(path);
        }

        const fileName = path.split('/').pop(); // Get file extension
        const fileExtension = path.split('.').pop().toLowerCase(); // Get file extension
        const imageUrl = '<?= base_url('api/file-manager/view-file') ?>/' + encodeURIComponent(hexEncode(path));

        // UI
        const viewModalKonten = document.getElementById('viewModalKonten');
        document.getElementById('viewModalLabel').innerHTML = fileName;

        if (viewModalKonten.classList.contains("is-hidden")) {
            viewModalKonten.classList.remove('is-hidden');
        }

        // Define HTML content based on file extension
        let contentHTML = '';
        let isEditable = false;

        if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico'].includes(fileExtension)) {
            // Display image files
            contentHTML = `<img src="${imageUrl}" class="w-100" alt="Image Preview">`;
        } else if (['mp4', 'webm', 'ogg'].includes(fileExtension)) {
            // Display video files
            contentHTML = `<video src="${imageUrl}" class="w-100" controls></video>`;
        } else if (['mp3', 'wav', 'ogg'].includes(fileExtension)) {
            // Display audio files
            contentHTML = `<audio src="${imageUrl}" class="w-100" controls></audio>`;
        } else if (['txt', 'log', 'md', 'json', 'php', 'js', 'css', 'ts', 'tsx', 'html', 'htm',
                'xml', 'yml', 'yaml', 'ini', 'conf', 'bat', 'sh', 'c', 'cpp', 'h', 'hpp',
                'py', 'rb', 'java', 'cs', 'swift', 'rs', 'go', 'pl', 'ps1', 'svelte',
                'scss', 'sass', 'less', 'sql', 'r', 'dockerfile', 'env'
            ]
            .includes(fileExtension)) {
            // Display text files in editable mode
            fetch(imageUrl)
                .then(response => response.text())
                .then(text => {
                    isEditable = true; // Set editable to true

                    // Update UI
                    document.getElementById('fileEditor').value = `${text}`;

                    if (document.getElementById('monaco').classList.contains("is-hidden")) {
                        document.getElementById('monaco').classList.remove('is-hidden');
                    }
                    if (!viewModalKonten.classList.contains("is-hidden")) {
                        viewModalKonten.classList.add('is-hidden');
                    }

                    document.getElementById('saveButton').style.display = 'block'; // Show Save button
                    openModal(document.getElementById('viewModal')); // Reopen the view modal

                    // Retrieve the editor instance by container ID (e.g., "monaco")
                    const editor = window.monaco.editor;

                    // Set editor language
                    let language = 'plaintext';
                    switch (fileExtension) {
                        case "json":
                            language = 'javascript';
                            break;
                        case "htm":
                        case "html":
                            language = 'html';
                            break;
                        case "php":
                            language = 'php';
                            break;
                        case "js":
                            language = 'javascript';
                            break;
                        case "css":
                            language = 'css';
                            break;
                        case "ts":
                        case "tsx":
                            language = 'typescript';
                            break;
                        case "xml":
                            language = 'xml';
                            break;
                        case "yml":
                        case "yaml":
                            language = 'yaml';
                            break;
                        case "ini":
                        case "conf":
                            language = 'ini';
                            break;
                        case "bat":
                            language = 'bat';
                            break;
                        case "sh":
                            language = 'shell';
                            break;
                        case "c":
                        case "h":
                            language = 'c';
                            break;
                        case "cpp":
                        case "hpp":
                            language = 'cpp';
                            break;
                        case "py":
                            language = 'python';
                            break;
                        case "rb":
                            language = 'ruby';
                            break;
                        case "java":
                            language = 'java';
                            break;
                        case "cs":
                            language = 'csharp';
                            break;
                        case "swift":
                            language = 'swift';
                            break;
                        case "rs":
                            language = 'rust';
                            break;
                        case "go":
                            language = 'go';
                            break;
                        case "pl":
                            language = 'perl';
                            break;
                        case "ps1":
                            language = 'powershell';
                            break;
                        case "scss":
                        case "sass":
                            language = 'scss';
                            break;
                        case "less":
                            language = 'less';
                            break;
                        case "sql":
                            language = 'sql';
                            break;
                        case "r":
                            language = 'r';
                            break;
                        case "md":
                            language = 'markdown';
                            break;
                        case "dockerfile":
                            language = 'dockerfile';
                            break;
                        default:
                            language = 'plaintext';
                            break;
                    }

                    // Set the language of the editor
                    window.monaco.getMonaco().editor.setModelLanguage(editor.getModel(), language);

                    // Set the editor's value to the file content
                    editor.getModel().setValue(`${text}`);

                });
            return;
        } else {
            // Other file types, provide a download option
            contentHTML = `<p><?= lang('Admin.previewUnavailable') ?></p>
            <a href="${imageUrl}" class="button is-primary" download><?= lang('Admin.downloadFile') ?></a>`;
        }

        // Insert content and display modal
        document.getElementById('viewModalKonten').innerHTML = contentHTML;
        document.getElementById('saveButton').style.display = isEditable ? 'block' : 'none'; // Show or hide Save button
        openModal(document.getElementById('viewModal')) // Open view modal
    }

    function refreshFileList() {
        listFiles(window.currentPath);
    }

    function listFiles(path = '') {
        window.currentPath = path; // Update the current path

        /* UI */
        if (document.getElementById('loaderBody').classList.contains("is-hidden")) {
            document.getElementById('loaderBody').classList.remove('is-hidden');
        }
        /* End of UI */

        fetch('<?= base_url('api/file-manager/list-files/') ?>' + encodeURIComponent(hexEncode(path)))
            .then(response => {
                if (!response.ok) {
                    // If the response isn't OK, try extracting the JSON error message and throw it.
                    return response.json().then(errorData => {
                        // You can throw the error data (or just the message) here.
                        throw errorData;
                    });
                }
                return response.json();
            })
            .then(data => {
                // console.log(data);

                /* UI */
                if (!document.getElementById('loaderBody').classList.contains("is-hidden")) {
                    document.getElementById('loaderBody').classList.add('is-hidden');
                }
                /* End of UI */

                if (data.error) {
                    throw data; // This will be caught by the catch below
                }

                // Sort folders first
                data.sort((a, b) => b.is_dir - a.is_dir);

                // Generate file table with a back button
                let table = ``;

                // Back button ("..") to go up one level
                if (path) {
                    const upPath = path.split('/').slice(0, -1).join('/');
                    table += `
                        <tr>
                            <td></td>
                            <td>
                                <a href="#" class="file-link" data-path="${upPath}" data-type="folder" onclick="event.preventDefault()">
                                    <span class="mr-2"><i class="fas fa-arrow-left"></i></span>..
                                </a>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>`;
                }

                // If no files/folders are found, add a message
                if (data.length === 0) {
                    table += `
                        <tr>
                            <td colspan="5" class="text-center"><?= lang('Admin.noFileOrFolderFound') ?></td>
                        </tr>`;
                } else {
                    // Loop through files and folders to display
                    // Inside .then(data => { /* process data */ })
                    data.forEach(file => {
                        let icon = file.is_dir ? '<i class="fas fa-folder"></i>' : getIconByExtension(file.name.split('.').pop().toLowerCase());
                        let isDir = file.is_dir ? 'Folder' : 'File';
                        let dateModified = file.modified_date || '-';
                        let permissions = file.permissions || '-'; // New permissions field

                        let actionBtns = file.is_dir ?
                            `<span class="btn-action-tooltip" data-tippy-content="<?= lang('Admin.open') ?>"><button class="button is-primary is-small btn-action" data-action="open" data-path="${file.path}"><i class="fa-solid fa-arrow-right"></i></button></span>` :
                            `<span class="btn-action-tooltip" data-tippy-content="<?= lang('Admin.view') ?>"><button class="button is-secondary is-small btn-action" data-action="view" data-path="${file.path}"><i class="fa-solid fa-eye"></i></button></span>
                            <span class="btn-action-tooltip" data-tippy-content="<?= lang('Admin.download') ?>"><button class="button is-secondary is-small btn-action" data-action="download" data-path="${file.path}"><i class="fa-solid fa-download"></i></button></span>`;

                        actionBtns += `
                            <span class="btn-action-tooltip" data-tippy-content="<?= lang('Admin.copy') ?>"><button class="button is-secondary is-small btn-action" data-action="copy" data-path="${file.path}"><i class="fa-solid fa-copy"></i></button></span>
                            <span class="btn-action-tooltip" data-tippy-content="<?= lang('Admin.move') ?>"><button class="button is-secondary is-small btn-action" data-action="move" data-path="${file.path}"><i class="fa-solid fa-scissors"></i></button></span>
                            <span class="btn-action-tooltip" data-tippy-content="<?= lang('Admin.rename') ?>"><button class="button is-secondary is-small btn-action" data-action="rename" data-path="${file.path}"><i class="fa-solid fa-i-cursor"></i></button></span>`;

                        // Add row with new permissions column
                        table += `
                            <tr>
                                <td>
                                    <div>
                                        <input class="file-checkbox" type="checkbox" data-path="${file.path}" />
                                    </div>
                                </td>
                                <td>
                                    <a href="#" class="file-link" data-path="${file.path}" data-type="${file.is_dir ? 'folder' : 'file'}" onclick="event.preventDefault()">
                                        <span class="mr-2">${icon}</span>${file.name} (${isDir})
                                    </a>
                                </td>
                                <td>${file.size}</td>
                                <td>${permissions}</td> <!-- Permissions column -->
                                <td>${dateModified}</td>
                                <td style="white-space: nowrap;">${actionBtns}</td>
                            </tr>`;
                    });

                }

                // Replace content of fileList to reset old event listeners
                const fileListContainer = document.getElementById('fileList');
                const newContent = document.createElement('tbody');
                newContent.innerHTML = table;
                fileListContainer.replaceWith(newContent);
                newContent.id = 'fileList';

                // "Select All" checkbox event
                document.getElementById('selectAll').addEventListener('click', function() {
                    const checkboxes = document.querySelectorAll('.file-checkbox');
                    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                });

                // Initialize tippy tooltips for .btn-action
                tippy('.btn-action-tooltip');

                // Event listener for actions using delegation
                newContent.addEventListener('click', function(event) {
                    const target = event.target.closest('button, a');

                    if (!target) return;

                    const action = target.getAttribute('data-action');
                    const path = target.getAttribute('data-path');
                    const type = target.getAttribute('data-type');

                    if (action) {
                        if (action === 'open') {
                            listFiles(path);
                        } else if (action === 'view') {
                            viewFile(path);
                        } else if (action === 'download') {
                            downloadFile(path);
                        } else if (action === 'copy') {
                            addToClipboard(path, 'copy');
                        } else if (action === 'move') {
                            addToClipboard(path, 'move');
                        } else if (action === 'back') {
                            listFiles(path);
                        } else if (action === 'rename') {
                            renameFile(path);
                        }
                    } else if (type === 'folder') {
                        listFiles(path);
                    } else if (type === 'file') {
                        viewFile(path);
                    }
                });
            })
            .catch(error => {
                // console.error(error);

                /* UI */
                if (!document.getElementById('loaderBody').classList.contains("is-hidden")) {
                    document.getElementById('loaderBody').classList.add('is-hidden');
                }
                // Show the error message using SweetAlert2 toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: error.message || 'An unexpected error occurred',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });
                /* End of UI */

            });;
    }

    function createFile() {
        Swal.fire({
            title: "<?= lang('Admin.enterNewFileNameWithExtension') ?>:",
            input: "text",
            inputAttributes: {
                autocapitalize: "off"
            },
            showCancelButton: true,
            cancelButtonText: "<?= lang('Admin.cancel') ?>",
            confirmButtonText: "<?= lang('Admin.save') ?>",
            // confirmButtonColor: "var(--mdb-primary)",
            showLoaderOnConfirm: true,
            preConfirm: (fileName) => {
                if (!fileName) {
                    Swal.showValidationMessage("<?= lang('Admin.failedToCreateFile') ?>: <?= lang('Validation.required', ['field' => lang('Admin.name')]) ?>");
                    return;
                }
                return fetch('<?= base_url('api/file-manager/create-file') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            path: window.currentPath,
                            fileName: fileName
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.status) {
                            Swal.showValidationMessage(
                                "<?= lang('Admin.failedToCreateFile') ?>: " + data.error
                            );
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value.status) {
                Swal.fire({
                    icon: 'success',
                    title: result.value.status
                });
                listFiles(window.currentPath); // Refresh the list to show the new file
            }
        });
    }

    function createFolder() {
        Swal.fire({
            title: "<?= lang('Admin.enterNewFolderName') ?>:",
            input: "text",
            inputAttributes: {
                autocapitalize: "off"
            },
            showCancelButton: true,
            cancelButtonText: "<?= lang('Admin.cancel') ?>",
            confirmButtonText: "<?= lang('Admin.save') ?>",
            // confirmButtonColor: "var(--mdb-primary)",
            showLoaderOnConfirm: true,
            preConfirm: (folderName) => {
                if (!folderName) {
                    Swal.showValidationMessage("<?= lang('Admin.failedToCreateFolder') ?>: <?= lang('Validation.required', ['field' => lang('Admin.name')]) ?>");
                    return;
                }
                return fetch('<?= base_url('api/file-manager/create-folder') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            path: window.currentPath,
                            folderName: folderName
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.status) {
                            Swal.showValidationMessage(
                                "<?= lang('Admin.failedToCreateFolder') ?>: " + data.error
                            );
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value.status) {
                Swal.fire({
                    icon: 'success',
                    title: result.value.status
                });
                listFiles(window.currentPath); // Refresh the list to show the new folder
            }
        });
    }

    function renameFile(oldPath) {
        // Extract the filename from oldPath
        const oldFileName = oldPath.split('/').pop();

        Swal.fire({
            title: "<?= lang('Admin.enterNewFileNameWithExtension') ?>:",
            input: "text",
            inputValue: oldFileName, // Set old filename as the default input value
            inputAttributes: {
                autocapitalize: "off"
            },
            showCancelButton: true,
            cancelButtonText: "<?= lang('Admin.cancel') ?>",
            confirmButtonText: "<?= lang('Admin.save') ?>",
            // confirmButtonColor: "var(--mdb-primary)",
            showLoaderOnConfirm: true,
            preConfirm: (newName) => {
                if (!newName) {
                    Swal.showValidationMessage("<?= lang('Admin.failedToRenameFile') ?>: <?= lang('Validation.required', ['field' => lang('Admin.name')]) ?>");
                    return;
                }
                return fetch('<?= base_url('api/file-manager/rename') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            oldPath: oldPath,
                            newName: newName
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.status) {
                            Swal.showValidationMessage(
                                "<?= lang('Admin.failedToRenameFile') ?>: " + data.error
                            );
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value.status) {
                Swal.fire({
                    icon: 'success',
                    title: result.value.status
                });
                listFiles(window.currentPath); // Refresh the list to show renamed file
            }
        });
    }

    // Helper function to get icon by file extension
    function getIconByExtension(ext) {
        switch (ext) {
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                return '<i class="fas fa-file-image"></i>';
            case 'mp4':
            case 'mkv':
            case 'webm':
                return '<i class="fas fa-file-video"></i>';
            case 'mp3':
            case 'wav':
                return '<i class="fas fa-file-audio"></i>';
            case 'pdf':
                return '<i class="fas fa-file-pdf"></i>';
            case 'doc':
            case 'docx':
                return '<i class="fas fa-file-word"></i>';
            case 'xls':
            case 'xlsx':
                return '<i class="fas fa-file-excel"></i>';
            case 'ppt':
            case 'pptx':
                return '<i class="fas fa-file-powerpoint"></i>';
            case 'zip':
            case 'rar':
            case '7z':
                return '<i class="fas fa-file-archive"></i>';
            case 'txt':
            case 'md':
            case 'log':
                return '<i class="fas fa-file-alt"></i>';
            case 'js':
            case 'css':
            case 'html':
            case 'php':
                return '<i class="fas fa-file-code"></i>';
            default:
                return '<i class="fas fa-file"></i>';
        }
    }

    // Save button functionality
    function saveFile(path) {

        /* UI */
        if (document.getElementById('loaderModal').classList.contains("is-hidden")) {
            document.getElementById('loaderModal').classList.remove('is-hidden');
        }
        /* End of UI */

        const updatedContent = document.getElementById('fileEditor').value;
        fetch('<?= base_url('api/file-manager/save-file') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    path: encodeURIComponent(hexEncode(path)), // Encode the file path
                    content: updatedContent,
                }),
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);

                /* UI */
                if (!document.getElementById('loaderModal').classList.contains("is-hidden")) {
                    document.getElementById('loaderModal').classList.add('is-hidden');
                }
                /* End of UI */

                if (data.success) {
                    // Show success toast 
                    Swal.fire({
                        position: "top",
                        icon: "success",
                        toast: true,
                        title: "<?= lang('Admin.fileSavedSuccessfully') ?>",
                        showConfirmButton: false,
                        timer: 1500,
                    });
                } else {
                    // Show error toast 
                    Swal.fire({
                        icon: "error",
                        toast: true,
                        title: "<?= lang('Admin.failedToSaveFile') ?>: " + data.error,
                        confirmButtonText: "<?= lang('Admin.close') ?>"
                    });
                }
            });
    }

    function deleteSelectedFiles() {
        const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
            .map(checkbox => checkbox.getAttribute('data-path'));

        if (selectedFiles.length === 0) {
            // Show error toast 
            Swal.fire({
                icon: "error",
                toast: true,
                title: "<?= lang('Admin.selectFilesToDelete') ?>",
                showConfirmButton: false,
                timer: 1500,
            });
            return;
        }

        Swal.fire({
            title: '<?= lang('Admin.deleteSelected') ?>',
            text: '<?= lang('Admin.deletedItemsCannotBeRecovered') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--bulma-primary) !important',
            confirmButtonText: '<?= lang('Admin.delete') ?>',
            cancelButtonText: '<?= lang('Admin.cancel') ?>',
        }).then((result) => {
            if (result.isConfirmed) {

                // Request item deletion
                fetch('<?= base_url('api/file-manager/delete-files') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            files: selectedFiles
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            // Show success alert dialog
                            Swal.fire({
                                position: "top-end",
                                icon: "success",
                                title: "<?= lang('Admin.deletedSuccessfully') ?>",
                                showConfirmButton: false,
                                timer: 1500,
                            });
                            listFiles(window.currentPath); // Refresh file list
                        } else if (data.error) {
                            // Show error alert dialog
                            Swal.fire({
                                position: "top-end",
                                icon: "error",
                                title: "<?= lang('Admin.failedToDelete') ?>: " + data.error,
                                confirmButtonText: "<?= lang('Admin.close') ?>",
                            });
                        }
                    })
                    .catch(error => console.error("Error deleting files:", error));

            }
        });

    }

    function getSelectedFiles() {
        return Array.from(document.querySelectorAll('.file-checkbox:checked'))
            .map(checkbox => checkbox.getAttribute('data-path'));
    }

    function compressSelectedFiles() {
        const selectedFiles = getSelectedFiles();

        if (selectedFiles.length === 0) {
            Swal.fire({
                icon: "error",
                toast: true,
                title: "<?= lang('Admin.selectFileToCompressZIP') ?>",
                showConfirmButton: false,
                timer: 1500,
            });
            return;
        }

        /* UI */
        if (document.getElementById('loaderBody').classList.contains("is-hidden")) {
            document.getElementById('loaderBody').classList.remove('is-hidden');
        }
        /* End of UI */

        fetch('<?= base_url('api/file-manager/compress') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    files: selectedFiles,
                    path: window.currentPath
                })
            })
            .then(response => response.json())
            .then(data => {

                /* UI */
                if (!document.getElementById('loaderBody').classList.contains("is-hidden")) {
                    document.getElementById('loaderBody').classList.add('is-hidden');
                }
                /* End of UI */

                if (data.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?= lang('Admin.successfullyCompressed') ?>',
                        text: data.archive
                    });
                    listFiles(window.currentPath); // Refresh list to show new zip file
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= lang('Admin.error') ?>',
                        text: "<?= lang('Admin.failedToCompressFile') ?>: " + data.error
                    });
                }
            })
            .catch(error => console.error('Error compressing files:', error));
    }

    function extractSelectedFiles() {
        const selectedFiles = getSelectedFiles();

        if (selectedFiles.length !== 1 || !selectedFiles[0].endsWith('.zip')) {
            Swal.fire({
                icon: "error",
                toast: true,
                title: "<?= lang('Admin.selectZipFileToEctract') ?>",
                showConfirmButton: false,
                timer: 1500,
            });
            return;
        }

        /* UI */
        if (document.getElementById('loaderBody').classList.contains("is-hidden")) {
            document.getElementById('loaderBody').classList.remove('is-hidden');
        }
        /* End of UI */

        fetch('<?= base_url('api/file-manager/extract') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    path: selectedFiles[0]
                })
            })
            .then(response => response.json())
            .then(data => {

                /* UI */
                if (!document.getElementById('loaderBody').classList.contains("is-hidden")) {
                    document.getElementById('loaderBody').classList.add('is-hidden');
                }
                /* End of UI */

                if (data.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?= lang('Admin.successfullyExtracted') ?>',
                        toast: true,
                        showConfirmButton: false,
                        timer: 1500,
                    });
                    listFiles(window.currentPath); // Refresh to show extracted files
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: "<?= lang('Admin.failedToExtractFile') ?>: " + data.error
                    });
                }
            })
            .catch(error => console.error("Error extracting file:", error));
    }

    function copySelectedFiles() {
        const selectedFiles = getSelectedFiles();
        if (selectedFiles.length === 0) {
            // Show error toast 
            Swal.fire({
                icon: "error",
                toast: true,
                title: "<?= lang('Admin.selectFilesToCopy') ?>",
                showConfirmButton: false,
                timer: 1500,
            });
            return;
        }
        setClipboard(selectedFiles, 'copy');
    }

    function moveSelectedFiles() {
        const selectedFiles = getSelectedFiles();
        if (selectedFiles.length === 0) {
            // Show error toast 
            Swal.fire({
                icon: "error",
                toast: true,
                title: "<?= lang('Admin.selectFilesToMove') ?>",
                showConfirmButton: false,
                timer: 1500,
            });
            return;
        }
        setClipboard(selectedFiles, 'move');
    }

    function setClipboard(files, action) {
        fetch('<?= base_url('api/file-manager/set-clipboard') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    files,
                    action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    // Show success toast 
                    Swal.fire({
                        icon: "success",
                        toast: true,
                        title: `${action.charAt(0).toUpperCase() + action.slice(1)}: <?= lang('Admin.copiedSuccessfullyReadyToPaste') ?>`,
                        showConfirmButton: false,
                        timer: 1500,
                    });
                } else {
                    // Show error toast 
                    Swal.fire({
                        icon: "error",
                        toast: true,
                        title: "<?= lang('Admin.failedToCopy') ?>: " + data.error,
                        showConfirmButton: false,
                        timer: 1500,
                    });
                }
            })
            .catch(error => console.error("Error setting clipboard:", error));
    }
</script>

<script>
    function hexEncode(input) {
        let hex = '';
        for (let i = 0; i < input.length; i++) {
            let code = input.charCodeAt(i).toString(16);
            // Ensure each code is two characters (pad with a leading zero if needed)
            if (code.length < 2) {
                code = '0' + code;
            }
            hex += code;
        }
        return hex;
    }

    function hexDecode(input) {
        let str = '';
        // Ensure the input has an even length
        for (let i = 0; i < input.length; i += 2) {
            const hexChunk = input.substr(i, 2);
            const charCode = parseInt(hexChunk, 16);
            str += String.fromCharCode(charCode);
        }
        return str;
    }
</script>

<script type="module">
    import MonacoEditorWrapper from '<?= base_url('assets/js/admin/MonacoEditorWrapper.js') ?>';

    window.monaco = new MonacoEditorWrapper({
        editorContainerId: "monaco",
        textareaId: "fileEditor",
        onSave: function(editor) {
            saveFile(window.currentFile);
        },
        language: "javascript",
    });
</script>
<?= $this->endSection() ?>