<?php

namespace Modules\PagingSystem;

log_message('debug', 'Paging System module initialized.');

// Loop through all PHP files in the /init folder
// and require them to initialize the module
foreach (glob(__DIR__ . '/init/*.php') as $file) {
    require_once $file;
}
