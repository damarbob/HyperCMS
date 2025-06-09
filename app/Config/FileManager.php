<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class FileManager extends BaseConfig
{
    /**
     * We can use placeholders like "{FCPATH}/foo/bar"
     * or "{ROOTPATH}/baz" – they'll be normalized.
     * 
     * @IMPORTANT:
     * Make sure the directory exists!
     */
    public string $productionPath      = '{FCPATH}'; // For production use, FCPATH is typically the public folder.
    public string $nonProductionPath   = '{ROOTPATH}'; // For development use, ROOTPATH could reference the application root.

    public function __construct()
    {
        parent::__construct();

        // Normalize both paths on instantiation
        $this->productionPath    = $this->normalizePath($this->productionPath);
        $this->nonProductionPath = $this->normalizePath($this->nonProductionPath);
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
