<?php
helper('hyper_hex');
$requester = hex_encode($uri);
?>

<?= $this->section('content') ?>
<?php if (!empty($entry)): // For edit action
?>
    <!-- History modal -->
    <div id="historyModal" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card is-fullheight">
            <section class="modal-card-body is-flex" style="--bulma-modal-card-body-padding: 0.5rem;">
                <iframe id="historyIframe" class="is-flex-grow-1" data-src="<?= base_url('admin/entry-data/' . $entry['id']) ?>" frameborder="0"></iframe>
            </section>
        </div>
        <button class="modal-close delete is-large" aria-label="close"></button>
    </div>
<?php endif; ?>

<!-- Modal for File Manager -->
<div id="fileManagerModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card is-fullheight">
        <div class="modal-card-body is-flex" style="--bulma-modal-card-body-padding: 0.5rem;">
            <iframe id="fileManagerIframe" class="is-flex-grow-1" data-src="<?= base_url("admin/file-manager?requester_id={$requester}") ?>" frameborder="0"></iframe>
        </div>
    </div>
    <button class="modal-close is-large" aria-label="close"></button>
</div>
<?= $this->endSection() ?>

<script>
    /**
     * Wait for an element with the given ID to be added to the DOM.
     * @param {string} fieldId - The field id to wait for.
     * @param {Function} callback - The callback to execute when the element is found.
     * @param {number} timeout - Optional maximum time in milliseconds to wait (default 1000).
     */
    function waitForElement(fieldId, callback, timeout = 1000) {
        const interval = 50; // How frequently to check.
        let elapsed = 0;

        function check() {
            const input = document.getElementById(fieldId);
            if (input) {
                callback(input);
            } else {
                elapsed += interval;
                if (elapsed < timeout) {
                    setTimeout(check, interval);
                } else {
                    console.warn(`Input with ID ${fieldId} not found after waiting ${timeout}ms.`);
                }
            }
        }

        check();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('hyper-fields-container');
        const metaInputCreator = window.hyper.factory.inputCreator({
            container: container,
            onFieldCreated: (fieldId) => {
                // Instead of using DOMContentLoaded (which only fires once),
                // we wait for the element to be available in the DOM.
                waitForElement(fieldId, (input) => {
                    // At this point, input is guaranteed to exist.
                    if (!input) { // (This check is extra safety – should not happen)
                        <?php if (ENVIRONMENT !== 'production'): ?>
                            console.warn(`Input with ID ${fieldId} not found.`);
                        <?php endif; ?>
                        return;
                    }

                    // Initialize TinyMCE if element has class 'hyper-rich-text-field'
                    if (input.classList.contains('hyper-rich-text-field')) {
                        initializeTinyMCE(fieldId);
                    } else if (input.type === 'url' && input.classList.contains('hyper-file-browse-field')) {
                        <?php if (ENVIRONMENT !== 'production'): ?>
                            console.log('Found file browse field:', fieldId);
                        <?php endif; ?>

                        // Create a new "Browse file" button with Bulma styling and a FA icon.
                        const browseBtn = document.createElement('button');
                        browseBtn.type = 'button';
                        browseBtn.className = 'button mt-2'; // Adjust classes as needed.
                        browseBtn.innerHTML = '<span class="icon"><i class="fa-solid fa-folder-open"></i></span><span><?= lang("ADmin.fileManager") ?></span>';

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

                        // Listen for messages from the file manager. (Attach this listener globally if needed.)
                        window.addEventListener('message', function(event) {

                            // Validate event.origin for extra security.
                            if (!window.hyper.util.uri.areUrisEqual(event.origin, '<?= base_url() ?>')) return;

                            if (event.data && event.data.action === 'filesSelected_r<?= $requester ?>') {
                                const selectedFiles = event.data.data; // Array of URL strings.
                                if (selectedFiles.length > 0) {
                                    // Insert the first selected file URL into the input.
                                    input.value = `<?= base_url('public/file-server/serve/') ?>${encodeURIComponent(window.hyper.util.hex.encode(selectedFiles[0]))}`;
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

        window.hyper_recreateMetaInputs = function() {
            // Destroy all TinyMCE instances before recreating inputs
            destroyTinyMCEInstances(document.querySelectorAll('.hyper-rich-text-field'));

            // Recreate the inputs
            metaInputCreator.create(<?= $processed_model_fields ?>);
        };

        window.hyper_populateMetaInputsWithHistory = function(data) {
            inputPopulatorInst.populate(data)
        }

        const inputPopulatorInst = window.hyper.factory.inputPopulator(container);
        inputPopulatorInst.populate(<?= isset($entry) ? $entry['fields'] : '' ?>);
    });
</script>
<script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<script>
    /**
     * Initializes TinyMCE for an editor input field.
     * @param id - The ID of the textarea element.
     */
    function initializeTinyMCE(id) {

        if (tinymce.get(id)) {
            tinymce.get(id)?.remove(); // Destroy existing instance
        }

        try {
            tinymce.init({
                skin: window.hyper_isDarkMode ? 'oxide-dark' : 'oxide',
                content_css: window.hyper_isDarkMode ? 'dark' : 'default',
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
                setup: function(editor) {
                    editor.on('change', function() {
                        editor.save();
                    });
                },
            });
        } catch (e) {
            console.error('TinyMCE initialization error:', e);
        }
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
    const hyperFormSubmit = hyperForm.querySelectorAll('input[type="submit"], button[type="submit"]');

    // Intercept the form submission, compute meta data, inject it, then let it submit.
    hyperForm.addEventListener("submit", function(event) {
        event.preventDefault();

        // Disable all input type submit
        hyperFormSubmit.forEach((el) => {
            el.disabled = true;
        });

        // Before building FormData, force TinyMCE to save editor content back to the textarea.
        tinymce.triggerSave();

        // Create a FormData object from the form (this grabs all the form elements including files)
        const fd = new FormData(this);

        /** @type {FormData} */
        const newFormData = window.hyper.util.form.encodeFormInputsToJson("fields", this);
        newFormData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        <?php if ($action === 'new'): ?>
            newFormData.append('model_id', '<?= $model['id'] ?>');
        <?php endif ?>

        // Send the new FormData using fetch.
        fetch(this.action, {
                method: this.method,
                headers: {
                    'Accept': 'application/json'
                },
                body: newFormData
            })
            .then(response => response.json())
            .then((data) => {
                // Enable all input type submit
                hyperFormSubmit.forEach((el) => {
                    el.disabled = false;
                });

                if (data.success) {
                    // If successful
                    window.hyper.factory.swal.success("<?= lang('Admin.success') ?>", {
                        text: data.success
                    }); // Show success message

                    <?php if ($action === 'new'): ?>
                        // Redirect the page after 1 second 
                        setTimeout(() => {
                            window.location.href = '<?= base_url('admin/entries') ?>';
                        }, 1000);
                    <?php endif ?>
                } else {
                    // If error
                    window.hyper.factory.swal.error("<?= lang('Admin.error') ?>", {
                        text: data.error
                    }); // Show error message
                }
            })
            .catch(err => console.error(err));
    });
</script>
<script>
    // Listen for messages from the file manager. (Attach this listener globally if needed.)
    window.addEventListener('message', function(event) {

        // Validate event.origin for extra security.
        if (!window.hyper.util.uri.areUrisEqual(event.origin, '<?= base_url() ?>')) return;

        if (event.data && event.data.action === 'entryDataSelected') {
            const selectedData = event.data.data; // Array of URL strings.
            useData(selectedData);
            // Close the modal after processing the selection.
            closeModal(document.getElementById('historyModal'));
        }
    });

    /**
     * Transforms an object into an array of objects with "id" and "value" properties.
     *
     * @param {Object} data - The input object with key/value pairs.
     * @returns {Array} Array of objects in the format [{ id: key, value: value }, ...]
     */
    function transformData(data) {
        return Object.entries(data).map(([key, value]) => ({
            id: key,
            value
        }));
    }

    function useData(selectedData) {
        // Check if any rows are selected
        if (selectedData.length > 0) {
            // Get the first selected row's ID and model name
            var id = selectedData[0].id;
            var modelName = selectedData[0].model_name;

            // Recreate the meta inputs and populate them with the selected data
            window.hyper_recreateMetaInputs();
            window.hyper_populateMetaInputsWithHistory(transformData(selectedData[0]));

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

            window.hyper.factory.swal.success("<?= lang('Admin.success') ?>");

        } else {
            window.hyper.factory.swal.error("<?= lang('Admin.selectRow') ?>");
        }
    }

    function showHistoryModal() {
        // Open the history modal.
        openModal(document.getElementById('historyModal'));

        // Lazy load the iframe source if it hasn't been loaded already.
        const iframe = document.getElementById('historyIframe');
        if (!iframe.getAttribute('src')) {
            iframe.setAttribute('src', iframe.getAttribute('data-src'));
        }
    }

    function deleteEntry() {
        window.hyper.factory.swal.confirm({
            title: "<?= lang('Admin.areYouSure') ?>",
            text: "<?= lang('Admin.youWillNotBeAbleToRevertThis') ?>",
            confirmButtonColor: "var(--bulma-danger)",
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').submit();
            }
        });
    }
</script>