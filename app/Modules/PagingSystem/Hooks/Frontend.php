<?php

use App\Entities\HyperHook;

return [
    'main' => new HyperHook('frontend:main', 'Main Frontend', 'This hook is used to add custom content to the main section.'),
    'head' => new HyperHook('frontend:head', 'Head Frontend', 'This hook is used to add custom content to the head section.'),
    'header' => new HyperHook('frontend:header', 'Header Frontend', 'This hook is used to add custom content to the header section.'),
    'footer' => new HyperHook('frontend:footer', 'Footer Frontend', 'This hook is used to add custom content to the footer section.'),
    //...
];
