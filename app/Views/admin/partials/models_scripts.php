<?= $this->section('content') ?>
<?php if ($action === 'edit'): ?>
    <!-- History modal -->
    <div id="historyModal" class="modal">
        <div class="modal-background"></div>
        <div class="modal-card is-fullheight">
            <section class="modal-card-body is-flex" style="--bulma-modal-card-body-padding: 0.5rem;">
                <iframe id="historyIframe" class="is-flex-grow-1" data-src="<?= base_url('admin/model-data/' . $model['id']) ?>" frameborder="0"></iframe>
            </section>
        </div>
        <button class="modal-close delete is-large" aria-label="close"></button>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<script type="text/javascript">
    /**
     * -------------------------------------------------------------------
     * Global Message Listener for File Manager Messages
     * -------------------------------------------------------------------
     *
     * Listens for messages from the file manager and processes data
     * if the action is "modelDataSelected". It validates the origin to
     * ensure security and then updates the form fields and closes the
     * modal.
     */
    window.addEventListener("message", function(event) {
        // Validate event.origin for extra security using a helper function.
        if (!window.hyper.util.uri.areUrisEqual(event.origin, "<?= base_url() ?>")) return;

        // If the event has the expected action, process the selected data.
        if (event.data && event.data.action === "modelDataSelected") {
            /** @type {Array<Object>} */
            const selectedData = event.data.data; // Expected to be an array of objects.
            useData(selectedData);

            // Close the history modal after processing the selection.
            closeModal(document.getElementById("historyModal"));
        }
    });

    /**
     * -------------------------------------------------------------------
     * Function: useData
     * -------------------------------------------------------------------
     * Processes the selected data received from the file manager.
     *
     * @param {Array<Object>} selectedData - Array containing data objects.
     * Each object is expected to have properties: name, fields, and icon.
     */
    function useData(selectedData) {
        if (selectedData.length > 0) {
            // Retrieve properties from the first selected data item.
            const {
                name,
                fields,
                icon
            } = selectedData[0];

            <?php if (ENVIRONMENT !== 'production'): ?>
                console.log(name, fields, icon);
            <?php endif ?>

            // Update all elements with the name "name"
            document.getElementsByName("name").forEach((element) => {
                element.value = name;
            });

            // Update all elements with the name "fields" and trigger a change event.
            document.getElementsByName("fields").forEach((element) => {
                element.value = fields;
                element.dispatchEvent(new Event("change"));
            });

            // Update all elements with the name "icon". Here we assume these elements
            // are containers (for example, <span> or <div>) where innerHTML is appropriate.
            document.getElementsByName("icon").forEach((element) => {
                element.innerHTML = icon;
            });

            // Smoothly scroll the page to the top.
            window.scrollTo({
                top: 0,
                behavior: "smooth",
            });

            // Display a success message.
            window.hyper.factory.swal.success("<?= lang('Admin.success') ?>");
        } else {
            // If no rows are selected, display an error message.
            window.hyper.factory.swal.error("<?= lang('Admin.selectRow') ?>");
        }
    }

    /**
     * -------------------------------------------------------------------
     * Document Ready Setup
     * -------------------------------------------------------------------
     *
     * Wait for the DOM content to load and then initialize components:
     * - Update the icon preview on the icon input.
     * - Initialize the Monaco editor.
     */
    document.addEventListener("DOMContentLoaded", function() {
        // Update icon preview if the icon input element exists.
        const iconInput = document.querySelector("#iconInput");
        if (iconInput) {
            updateIconPreview(iconInput);
        }

        // Initialize the Monaco editor with specific options.
        const fieldsEditor = window.hyper.factory.monaco({
            editorContainerSelector: "#monaco",
            textareaSelector: "#fields",
            language: "json",
            onSave: function(editor) {
                const form = document.getElementById("formEditModel");
                if (form) {
                    form.submit();
                } else {
                    console.warn("Form id is not assigned to the editor");
                }
            },
        });
    });

    /**
     * -------------------------------------------------------------------
     * Function: showHistoryModal
     * -------------------------------------------------------------------
     * Opens the history modal and lazy-loads the iframe source if not already set.
     */
    function showHistoryModal() {
        // Open the history modal.
        openModal(document.getElementById("historyModal"));

        // Lazy load the iframe source if it's not already loaded.
        const iframe = document.getElementById("historyIframe");
        if (!iframe.getAttribute("src")) {
            iframe.setAttribute("src", iframe.getAttribute("data-src"));
        }
    }

    /**
     * -------------------------------------------------------------------
     * Function: updateIconPreview
     * -------------------------------------------------------------------
     * Updates the preview element with the icon class based on the input value.
     *
     * @param {HTMLElement} iconInput - The input element containing the icon value.
     */
    function updateIconPreview(iconInput) {
        const iconPreview = document.querySelector("#iconPreview");
        if (iconInput && iconPreview) {
            iconPreview.className = iconInput.value;
        }
    }

    /**
     * -------------------------------------------------------------------
     * Function: deleteModel
     * -------------------------------------------------------------------
     * Prompts the user for confirmation (using hyper.factory.swal) and submits the
     * delete form if confirmed.
     */
    function deleteModel() {
        window.hyper.factory.swal
            .confirm({
                title: "<?= lang('Admin.areYouSure') ?>",
                text: "<?= lang('Admin.youWillNotBeAbleToRevertThis') ?>",
                confirmButtonColor: "var(--bulma-danger)",
            })
            .then((result) => {
                if (result.isConfirmed) {
                    document.getElementById("deleteForm").submit();
                }
            });
    }
</script>