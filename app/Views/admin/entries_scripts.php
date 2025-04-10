<!-- Modal for File Manager -->
<div id="fileManagerModal" class="modal is-large">
    <div class="modal-background"></div>
    <div class="modal-content">
        <div class="card">
            <div class="card-content" style="--bulma-card-content-padding: 8px;">
                <iframe id="fileManagerIframe" data-src="<?= base_url('admin/file-manager') ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
            </div>
        </div>
    </div>
    <button class="modal-close is-large" aria-label="close"></button>
</div>

<script type="module" src="<?= base_url('assets/js/admin/use-case/Form.js') ?>"></script>
<script type="module">
    import InputCreator from '<?= base_url('assets/js/admin/InputCreator.js') ?>';
    import InputPopulator from '<?= base_url('assets/js/admin/InputPopulator.js') ?>';

    const container = document.getElementById('hyper-fields-container');
    const metaInputCreator = new InputCreator({
        container: container,
        onFieldCreated: (fieldId) => {

            // Wait for document to be fully loaded
            document.addEventListener("DOMContentLoaded", function() {

                // Retrieve the input element using the field ID.
                const input = document.getElementById(fieldId);

                if (!input) {
                    <?php if (ENVIRONMENT === 'development'): ?>
                        console.warn(`Input with ID ${fieldId} not found.`);
                    <?php endif; ?>
                    return;
                }

                // Initialize TinyMCE if element has class 'hyper-rich-text-field'
                if (input.classList.contains('hyper-rich-text-field')) {
                    initializeTinyMCE(fieldId);
                } else if (input.type === 'url' && input.classList.contains('hyper-file-browse-field')) {
                    console.log('Found file browse field:', fieldId);

                    // Create a new "Browse file" button with Bulma styling and a FA icon.
                    const browseBtn = document.createElement('button');
                    browseBtn.type = 'button';
                    browseBtn.className = 'button mt-2'; // Adjust classes as needed.
                    browseBtn.innerHTML = '<span class="icon"><i class="fa-solid fa-folder-open"></i></span><span><?= lang('ADmin.fileManager') ?></span>';

                    // Append the button immediately after the input element.
                    input.insertAdjacentElement('afterend', browseBtn);

                    // Attach an event listener to the "Browse file" button.
                    browseBtn.addEventListener('click', function() {
                        // Open the file manager modal.
                        openModal(document.getElementById('fileManagerModal'));

                        // Lazy load the iframe source if it hasn't been loaded already.
                        const iframe = document.getElementById('fileManagerIframe');
                        if (!iframe.getAttribute('src')) {
                            iframe.setAttribute('src', iframe.getAttribute('data-src'));
                        }
                    });

                    // Listen for messages from the file manager. (Note: if you have multiple file
                    // inputs on the same page, consider attaching this listener only once globally.)
                    window.addEventListener('message', function(event) {
                        // Optionally validate the event.origin for improved security.
                        if (event.data && event.data.mceAction === 'filesSelected') {
                            const selectedFiles = event.data.data; // Array of URL strings.
                            if (selectedFiles.length > 0) {
                                // Insert the first selected file URL into the input.
                                input.value = `<?= base_url('api/file-server/serve/') ?>${encodeURIComponent(hexEncode(selectedFiles[0]))}`;
                            }
                            // Close the modal after processing the selection.
                            closeModal(document.getElementById('fileManagerModal'));
                        }
                    });
                }
            });
        },

    });

    // Inject the fields as is. No need quotes mark. JS will treat the fields as arrays.
    metaInputCreator.create(<?= $processed_model_fields ?>);

    const inputPopulator = new InputPopulator(container);
    inputPopulator.populate(<?= isset($entry) ? $entry['fields'] : '' ?>);
</script>
<script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<script>
    /**
     * Initializes TinyMCE for an editor input field.
     * @param id - The ID of the textarea element.
     */
    function initializeTinyMCE(id) {
        // console.log("Initializing TinyMCE for:", id);

        if (tinymce.get(id)) {
            tinymce.get(id)?.remove(); // Destroy existing instance
        }

        setTimeout(() => {
            tinymce.init({
                skin: 'oxide-dark',
                content_css: 'dark',
                selector: `#${id}`,
                license_key: "gpl",
                relative_urls: false,
                document_base_url: "<?= base_url() ?>",
                external_plugins: {
                    fileinsert: `<?= base_url() ?>assets/js/tinymce/fileinsert-plugin.js`,
                },
                plugins: [
                    "advlist",
                    "autolink",
                    "image",
                    "lists",
                    "link",
                    "charmap",
                    "preview",
                    "anchor",
                    "searchreplace",
                    "fullscreen",
                    "insertdatetime",
                    "table",
                    "help",
                    "wordcount",
                    "fileinsert",
                    "code",
                ],
                toolbar: "fullscreen | fileinsert | undo redo | casechange blocks | bold italic backcolor | image | " +
                    "alignleft aligncenter alignright alignjustify | " +
                    "bullist numlist checklist outdent indent | removeformat | table | code | help",
                promotion: false,
            });
        }, 500);
    }

    function destroyTinyMCEInstances(editors) {
        editors.forEach((element) => {
            if (tinymce.get(element.id)) {
                tinymce.get(element.id)?.remove();
            }
        });
    }
</script>
<script>
    const hyperForm = document.getElementById("hyper-form");

    // Intercept the form submission, compute meta data, inject it, then let it submit.
    hyperForm.addEventListener("submit", function(event) {
        event.preventDefault();

        // Before building FormData, force TinyMCE to save editor content back to the textarea.
        tinymce.triggerSave();

        // Create a FormData object from the form (this grabs all the form elements including files)
        const fd = new FormData(this);

        const newFormData = new FormData();
        newFormData.encodeFormInputsToJson("fields", this);

        // Send the new FormData using fetch.
        fetch(this.action, {
                method: this.method,
                body: newFormData
            })
            .then(response => response.json())

            .then((data) => {
                if (data.success) {
                    // If successful
                    Swal.fire("<?= lang('Admin.success') ?>", data.message, "success"); // Show success message

                    // Redirect the page after 1 second if redirect url exists
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }
                } else {
                    // If error
                    Swal.fire("<?= lang('Admin.error') ?>", data.message, "error"); // Show error message
                }
            })
            .then(data => console.log(data))
            .catch(err => console.error(err));
    });
</script>
<script>
    function deleteModel() {
        Swal.fire({
            title: "<?= lang('Admin.areYouSure') ?>",
            text: "<?= lang('Admin.youWillNotBeAbleToRevertThis') ?>",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "var(--bulma-danger)",
            confirmButtonText: "<?= lang('Admin.yes') ?>",
            cancelButtonText: "<?= lang('Admin.cancel') ?>",
            theme: window.isDarkMode ? 'dark' : 'light',
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').submit();
            }
        });
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
</script>