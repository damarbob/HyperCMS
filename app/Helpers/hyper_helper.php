<?php
// hyper_helper.php

use Masterminds\HTML5;

if (!function_exists('map_entry_fields')) {
    /**
     * Converts a JSON string of entry fields to an associative array.
     *
     * This function expects a JSON-encoded string that represents an array of objects,
     * each containing an 'id' and a 'value' key. It then returns an associative array mapping
     * each field's id to its corresponding value.
     *
     * If the JSON string cannot be decoded or does not produce an array, this function returns an empty array.
     *
     * @param string $fieldsJson JSON encoded string of entry fields.
     *
     * @return array<string, mixed> Associative array where keys are field IDs and values are field values.
     */
    function map_entry_fields(string $fieldsJson): array
    {
        $decoded = json_decode($fieldsJson, true);

        // Return an empty array if decoding fails or the result is not an array.
        if (!is_array($decoded)) {
            return [];
        }

        return array_column($decoded, 'value', 'id');
    }
}

if (!function_exists('unmap_entry_fields')) {
    /**
     * Converts an associative array of entry fields to a JSON string.
     *
     * This function expects an associative array where each key is a field ID and its value is the field value.
     * It then builds an array of objects (each with 'id' and 'value' keys) and returns it as a JSON-encoded string.
     *
     * @param array<string, mixed> $fields Associative array mapping field IDs to field values.
     *
     * @return string JSON encoded string representing an array of field objects.
     */
    function unmap_entry_fields(array $fields): string
    {
        $result = [];
        foreach ($fields as $id => $value) {
            $result[] = [
                'id' => $id,
                'value' => $value,
            ];
        }
        return json_encode($result);
    }
}

if (!function_exists('serve_file')) {
    /**
     * Serves a file from a specified file path.
     *
     * This function performs the following:
     *   1. Loads the necessary hyper_hex helper to ensure the hex_encode() function is available.
     *   2. Retrieves the FileServer service via CodeIgniter's service locator.
     *   3. Encodes the provided file path using hex_encode().
     *   4. Delegates the file-serving logic to the FileServer service.
     *
     * The FileServer service's serve() method is expected to return an associative array
     * containing file information (such as content type and body).
     *
     * @param string $path The file path to be served.
     *
     * @return array An associative array as returned by the FileServer service's serve() method.
     */
    function serve_file(string $path): array
    {
        // Load the required helper to ensure hex_encode() is available.
        helper('hyper_hex');

        /** @var \App\Services\FileServer $fileServer */
        $fileServer = service('fileServer');

        // Encode the file path using the hex_encode helper function.
        $encodedPath = hex_encode($path);

        // Delegate to the FileServer service and return the result.
        return $fileServer->serve($encodedPath);
    }
}

if (!function_exists('render')) {
    /**
     * Renders a view and injects hyper data as a JavaScript variable.
     *
     * This function performs the following tasks:
     *   1. Adds a "hyper" key to the view data.
     *   2. Converts the hyper data to a JSON string with hex-encoding for problematic characters.
     *   3. Renders the view using CodeIgniter's view() helper.
     *   4. Loads the rendered HTML into an HTML5 DOM parser.
     *   5. Injects a <script> element into the <head> of the document containing the JSON data.
     *   6. Optionally calls a user-provided callback to modify the DOM.
     *   7. Returns the final modified HTML.
     *
     * @param string        $name       The view name.
     * @param array         $data       The data array passed to the view.
     * @param array         $options    Options to pass to the view() helper.
     * @param callable|null $modifyDom  Optional callback that receives the DOMDocument for further modifications.
     *
     * @return string The final rendered HTML.
     */
    function render(string $name, array $data = [], array $options = [], ?callable $modifyDom = null): string
    {
        // Prepare the hyper data: make it accessible as window.hyper in JavaScript.
        if (!empty($data['hyper']) && !empty($data['hyper']['config'])) {
            $data['hyper']['data'] = $data;

            // Convert the hyper data to JSON, making sure to hex-encode characters that might break the script.
            $jsonExport = json_encode($data['hyper'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            // Build the JavaScript snippet that assigns the JSON data to window.hyper.
            $jsonExportScript = <<<JS
                window.hyper = ($jsonExport);
                document.addEventListener('DOMContentLoaded', function() {
                    if (window.hyper.config.environment !== 'production') {
                        console.log('Hyper data:', window.hyper);
                    }
                });
            JS;
        }

        // Render the view using CI4's view() helper.
        $view = view($name, $data, $options);

        // Initialize the HTML5 parser with options.
        $html5 = new HTML5([
            'disable_html_ns' => true,
            'preserve_whitespace' => true,
        ]);

        // Load the rendered HTML into a DOMDocument.
        $dom = $html5->loadHTML($view);

        // Retrieve the <head> element.
        $head = $dom->getElementsByTagName('head')->item(0);

        // If the <head> element exists (it might not if this is a view partial),
        // create and insert our <script> element as the first child.
        if ($head && !empty($jsonExportScript)) {
            $script = $dom->createElement('script');
            $script->nodeValue = $jsonExportScript;
            $head->insertBefore($script, $head->firstChild);
        }

        // Allow a custom callback to modify the entire DOM before output.
        if (is_callable($modifyDom)) {
            $modifyDom($dom);
        }

        // Save the updated DOM back to HTML and return it.
        $view = $dom->saveHTML();

        return $view;
    }
}

if (!function_exists('dump_language_keys')) {
    /**
     * Dumps all language keys and their corresponding values for the current locale.
     *
     * This function leverages the CodeIgniter locator service to scan the folder
     * "app/Language/{locale}/" for PHP files. Each file is included and expected to return
     * an array of translations. The arrays are then merged into a single associative array.
     *
     * @return array An associative array of language keys and translations.
     */
    function dump_language_keys(): array
    {
        static $allLang = null;

        // Return the cached array if it has been built already.
        if ($allLang !== null) {
            return $allLang;
        }

        // Get the current locale (e.g., 'en' or 'fr')
        $language = \Config\Services::language();
        $locale = $language->getLocale();

        // Use the locator service to list all PHP files in the language folder for the current locale.
        // Adjust this path if the language files reside in a custom location.
        $locator = service('locator');
        $files = $locator->listFiles("Language/{$locale}/");

        $allTranslations = [];

        // Loop through each found file, require it, and merge its translations.
        foreach ($files as $file) {
            // Include the file. Each language file should return an array of translations.
            $translations = require $file;
            if (is_array($translations)) {
                $allTranslations = array_merge($allTranslations, $translations);
            }
        }

        // Cache the result for subsequent calls.
        $allLang = $allTranslations;
        return $allTranslations;
    }
}

if (!function_exists('dump_language_keys_grouped')) {
    /**
     * Dumps all language keys and values for the current locale, grouped by file name.
     *
     * This function leverages the CodeIgniter locator service to scan the folder
     * "app/Language/{locale}/" for PHP files. It then loads each file (which should return
     * an array of translations) and uses the filename (without the ".php" extension)
     * as the group key. For example, translations in "Auth.php" become available via the
     * "Auth" key.
     *
     * @return array An associative array where each key is a language group and its
     *               value is an array of translation key/value pairs.
     */
    function dump_language_keys_grouped(): array
    {
        static $allLang = null;

        // Use cached translations if available.
        if ($allLang !== null) {
            return $allLang;
        }

        // Get the current locale (e.g., 'en', 'fr').
        $language = \Config\Services::language();
        $locale = $language->getLocale();

        // Retrieve the locator service.
        $locator = service('locator');

        // List all PHP files in the language folder for the current locale.
        // This assumes the language files are under "app/Language/{locale}/".
        $files = $locator->listFiles("Language/{$locale}/");

        $allTranslations = [];

        foreach ($files as $file) {
            // Use the filename (without extension) as the group key.
            $group = pathinfo($file, PATHINFO_FILENAME);

            // Each language file is expected to return an array of translations.
            $translations = require $file;

            if (is_array($translations)) {
                // If multiple files use the same group, merge their arrays.
                if (isset($allTranslations[$group])) {
                    $allTranslations[$group] = array_merge($allTranslations[$group], $translations);
                } else {
                    $allTranslations[$group] = $translations;
                }
            }
        }

        // Cache the result for future calls.
        $allLang = $allTranslations;

        return $allTranslations;
    }
}
