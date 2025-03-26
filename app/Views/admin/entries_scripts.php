<script type="module" src="<?= base_url('assets/js/admin/use-case/Form.js') ?>"></script>
<script type="module">
    import InputCreator from '<?= base_url('assets/js/admin/InputCreator.js') ?>';
    import InputPopulator from '<?= base_url('assets/js/admin/InputPopulator.js') ?>';

    const container = document.getElementById('hyper-fields-container');
    const metaInputCreator = new InputCreator(container);

    // Inject the fields as is. No need quotes mark. JS will treat the fields as arrays.
    metaInputCreator.create(<?= $processed_model_fields ?>);

    const inputPopulator = new InputPopulator(container);
    inputPopulator.populate(<?= isset($entry) ? $entry['fields'] : '' ?>);
</script>
<script src="<?= base_url('assets/js/vendor/tinymce/tinymce.min.js') ?>"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.hyper-rich-text-editor').forEach((element) => {
            initializeTinyMCE(element.id);
        });
    });

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
                external_plugins: {
                    dsmgallery: `<?= base_url() ?>assets/js/tinymce/dsmgallery-plugin.js`,
                    dsmfileinsert: `<?= base_url() ?>assets/js/tinymce/dsmfileinsert-plugin.js`,
                },
                dsmgallery_api_endpoint: `<?= base_url() ?>api/galeri`,
                dsmgallery_gallery_url: `<?= base_url() ?>admin/galeri`,
                dsmfileinsert_api_endpoint: `<?= base_url() ?>api/file`,
                dsmfileinsert_file_manager_url: `<?= base_url() ?>admin/file`,
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
                    "dsmgallery",
                    "dsmfileinsert",
                    "code",
                ],
                toolbar: "fullscreen | dsmgallery dsmfileinsert | undo redo | casechange blocks | bold italic backcolor | image | " +
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