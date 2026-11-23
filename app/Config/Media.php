<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Where the files of the media library are kept.
 *
 * The folder is outside the public folder, so a file cannot be requested by its path and nothing in it can run as
 * code. Files are served by the media controller, from their row in `hyper_files`.
 */
class Media extends BaseConfig
{
    /**
     * A folder on the server. `{WRITEPATH}` and `{ROOTPATH}` are replaced, and the folder is made when it is missing.
     * It must be writable by the web server, and a host that wants the files elsewhere sets `media.uploadPath` in `.env`.
     * A folder inside the public folder is refused, because the files would then be served by the web server itself.
     */
    public string $uploadPath = '{WRITEPATH}uploads';

    public function __construct()
    {
        parent::__construct();

        $this->uploadPath = $this->normalisePath($this->uploadPath);
    }

    private function normalisePath(string $path): string
    {
        $path = preg_replace_callback('/\{(WRITEPATH|ROOTPATH)\}/', static fn (array $m): string => defined($m[1]) ? constant($m[1]) : $m[0], $path);

        return rtrim(str_replace('/', DIRECTORY_SEPARATOR, (string) $path), '/\\');
    }
}
