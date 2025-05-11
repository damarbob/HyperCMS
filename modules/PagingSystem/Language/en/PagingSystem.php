<?php

return [
    'moduleName' => 'Paging System',
    'moduleDescription' => 'A module to manage and edit pages with a custom editor.',

    // Editor
    'editor' => 'Editor',
    'editor-x' => 'Editor {x}',

    'openWithEditor' => 'Open with Editor',

    'primary' => 'Primary',
    'chosenPrimaryModelWillBeRoutedToTheFrontend' => "The chosen primary model will be routed to the frontend without a path prefix. For instance, a model named <b>Page</b> with an entry name/URL of <b>products</b> will be routed to <i>" . base_url('products') . "</i> instead of <i>" . base_url('page/products') . "</i>.",
    'assets' => 'Assets',
    'selectedModelWillServeAsPrimaryModelForServingAssets' => 'The selected model will serve as the primary model for serving assets on the frontend.',
    'meta' => 'Meta',
    'selectedMetaModelWillBeUsedToInject' => 'The selected meta model will be used to inject meta tags into pages.',
];
