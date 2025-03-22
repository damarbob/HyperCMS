<script src="https://cdn.jsdelivr.net/npm/grapesjs@0.22.5/dist/grapes.min.js" integrity="sha256-/WoKyVG/rkPGHHHqcWUCUrZEAv4MNxbHL1wcN7Y5o30=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-flexbox@1.0.1/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-blocks-basic@1.0.2/dist/index.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/grapesjs-custom-code@1.0.2/dist/index.min.js"></script>
<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.hyper-editor').forEach((element) => {
            console.log(element.id);

            // Hide the element
            element.style.display = 'none';

            // Initialize the editor after the element
            const gjsId = `gjs-${element.id}`;
            element.insertAdjacentHTML('afterend', `<div id='${gjsId}'></div>`);
            initializeEditor(gjsId);
        });
    });

    function initializeEditor(id) {
        var editor = grapesjs.init({
            container: '#' + id,
            canvas: {
                styles: [
                    'https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css',
                ],
            },
            storageManager: false,
            plugins: [
                myPlugin,
                'grapesjs-blocks-flexbox',
                'gjs-blocks-basic',
                'grapesjs-custom-code',
            ],
            pluginsOpts: {
                'gjs-blocks-basic': {
                    // options
                },
                'grapesjs-custom-code': {
                    // options
                },
            }
            // components: '<div class="txt-red">Hello world!</div>',
            // style: '.txt-red{color: red}',
        });

        editor.on('load', function() {
            // Get the canvas document
            const canvasDoc = editor.Canvas.getDocument();

            // Create a new <style> element
            const darkBgStyle = canvasDoc.createElement('style');
            darkBgStyle.innerHTML = `body { background-color: var(--bulma-scheme-main) !important; }`;

            // Append it to the canvas's head so it loads last
            canvasDoc.head.appendChild(darkBgStyle);
        });

        editor.Panels.addButton('options', {
            id: 'gjs-save-button',
            className: 'fa fa-save', // FontAwesome save icon classes
            command: 'gjs-save', // Ties the button to the above save command
            attributes: {
                title: 'Save',
            },
        });

        editor.Commands.add('gjs-save', {
            run(editor, sender) {
                // Deactivate the button if needed
                if (sender) sender.set('active', false);

                // Gather the necessary data from the editor
                const htmlOutput = editor.getHtml();
                const cssOutput = editor.getCss();
                const components = editor.getComponents(); // returns the JSON representation
                const projectData = editor.getProjectData();

                // Prepare data payload
                const payload = {
                    hyper_html: htmlOutput,
                    hyper_css: cssOutput,
                    hyper_component_elements: components,
                    hyper_page_project_data: projectData,
                };

                // Send data to your backend via AJAX (using fetch as an example)
                fetch('/your/backend/save', { // <-- Replace with your backend URL
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Save Successful:', data);
                        // Optionally, show a notification to the user
                    })
                    .catch(error => {
                        console.error('Error saving data:', error);
                        // Optionally, show an error notification to the user
                    });
            }
        });

    }

    function myPlugin(editor) {
        // Use the API: https://grapesjs.com/docs/api/
        <?php foreach ($test_components as $component): ?>
            editor.Blocks.add('<?= url_title($component['component_title']) ?>', {
                label: '<?= $component['component_title'] ?>',
                content: `<?= $component['component_content'] ?>`,
            });
        <?php endforeach; ?>
    }
</script>