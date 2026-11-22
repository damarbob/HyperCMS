<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class FileManager extends BaseConfig
{
    /**
     * We can use placeholders like "{FCPATH}/foo/bar"
     * or "{ROOTPATH}/baz" – they'll be normalized.
     * 
     * One root is used in every environment, so development never exposes more than production.
     * The name `productionPath` is kept so an existing `fileManager.productionPath` setting still applies.
     * A root that is the project folder, or a folder above it, is replaced by the public folder.
     *
     * @IMPORTANT:
     * The directory is created when it is missing.
     */
    public string $productionPath = '{FCPATH}'; // The public folder, or a folder inside it such as '{FCPATH}uploads'.

    public function __construct()
    {
        parent::__construct();

        // Normalize the path on instantiation
        $this->productionPath = $this->normalizePath($this->productionPath);
    }

    /**
     * Replace placeholders {FCPATH}, {ROOTPATH} with their constants,
     * then turn all "/" into DIRECTORY_SEPARATOR.
     */
    protected function normalizePath(string $path): string
    {
        // 1) Replace {FCPATH} or {ROOTPATH} via regex callback
        $path = preg_replace_callback(
            '/\{(FCPATH|ROOTPATH)\}/',
            function ($matches) {
                // $matches[1] is either "FCPATH" or "ROOTPATH"
                return defined($matches[1]) ? constant($matches[1]) : $matches[0];
            },
            $path
        );

        // 2) Convert any forward slashes to DIRECTORY_SEPARATOR
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
