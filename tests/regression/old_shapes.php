<?php
declare(strict_types=1);

// Models and entries that hold every shape the old admin form could store, for the upgrade test. They are added to
// the seed in the 0.2 layout, and the test then puts all of it into the 0.1 layout and upgrades it. They are kept
// out of seed.sql so the captures of the API and the admin keep describing the same data.
//
// A field here is written the way the old form wrote it, so the conversions of field definitions and values have
// the real shapes to meet. The keys are `models` (name, fields, user_groups, trashed) and `entries` (values).

$serve = static fn(string $path): string => 'http://localhost:8080/public/file-server/serve/' . bin2hex($path);
$escaped = static fn(string $text): string => str_replace('/', '\\/', $text);

return [
    [
        'name'   => 'Shapes',
        'fields' => [
            ['id' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'helper' => 'Shown in [base_url] lists'],
            ['id' => 'mail', 'label' => 'Mail', 'type' => 'email'],
            ['id' => 'body', 'label' => 'Body', 'type' => 'textarea', 'className' => 'hyper-rich-text-field'],
            ['id' => 'snippet', 'label' => 'Snippet', 'type' => 'textarea', 'className' => 'hyper-code-field', 'data' => ['language' => 'html']],
            ['id' => 'logo', 'label' => 'Logo', 'type' => 'url', 'className' => 'hyper-file-browse-field'],
            ['id' => 'photos', 'label' => 'Photos', 'type' => 'file', 'multiple' => true],
            ['id' => 'rating', 'label' => 'Rating', 'type' => 'range', 'options' => [['min' => 1, 'max' => 10]]],
            ['id' => 'count', 'label' => 'Count', 'type' => 'number', 'options' => ['step' => 1, 'min' => 0]],
            ['id' => 'price', 'label' => 'Price', 'type' => 'number', 'options' => ['step' => '0.01']],
            ['id' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'checked' => true],
            ['id' => 'size', 'label' => 'Size', 'type' => 'radio', 'options' => [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']]],
            ['id' => 'tags', 'label' => 'Tags', 'type' => 'checkboxes', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C']]],
            ['id' => 'kind', 'label' => 'Kind', 'type' => 'select', 'options' => [['value' => 'x', 'label' => 'X'], ['value' => 'y', 'label' => 'Y']]],
            ['id' => 'colour', 'label' => 'Colour', 'type' => 'color'],
            ['id' => 'starts', 'label' => 'Starts', 'type' => 'datetime-local'],
            ['id' => 'secret', 'label' => 'Secret', 'type' => 'hidden'],
        ],
        'entries' => [
            [
                'title' => 'First shape', 'mail' => 'a@example.test', 'body' => '<p>Rich <img src="' . $serve('uploads/upgrade-test/rich-1.png') . '"> <img src="' . $serve('uploads/upgrade-test/gone.png') . '"></p>', 'snippet' => '<b>code</b>',
                'logo' => $serve('uploads/upgrade-test/logo.svg'), 'photos' => ['uploads/upgrade-test/photo-one.png', 'uploads/upgrade-test/photo-two.png'],
                'rating' => '7', 'count' => '3', 'price' => '12.50', 'active' => 'on', 'size' => 'l', 'tags' => 'b', 'kind' => 'y', 'colour' => '#AA00FF', 'starts' => '2026-03-01T08:30',
            ],
            [
                'title' => 'Second shape', 'mail' => '', 'body' => '', 'snippet' => '', 'logo' => '', 'photos' => '',
                'rating' => '1', 'count' => '', 'price' => '', 'active' => 'off', 'tags' => ['a', 'c'], 'kind' => 'x', 'colour' => '#000000', 'starts' => '',
            ],
            ['title' => 'Odd values', 'count' => 'many', 'active' => 'maybe', 'starts' => 'soon', 'tags' => [['nested']], 'photos' => [['not', 'a path']]],
        ],
    ],
    [
        'name'   => 'Queries',
        'fields' => [
            ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
            ['id' => 'model', 'label' => 'Model', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'models', 'select' => 'id as value, name as label']]],
            ['id' => 'shape', 'label' => 'Shape', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'entries', 'select' => 'id as value, {{field:title}} as label', 'where' => "model_name = 'Shapes'"]]],
            ['id' => 'hook', 'label' => 'Hook', 'type' => 'select', 'options' => ['type' => 'data', 'content' => 'hooks']],
            ['id' => 'whole', 'label' => 'Whole', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'entries', 'select' => 'id as value, fields as label', 'where' => 'model_id = 1']]],
            ['id' => 'owner', 'label' => 'Owner', 'type' => 'select', 'options' => ['type' => 'data', 'content' => ['table' => 'users', 'select' => 'id as value, username as label']]],
        ],
        'entries' => [
            ['title' => 'Links', 'model' => '1', 'shape' => '', 'hook' => 'page.render', 'whole' => '2', 'owner' => '1'],
        ],
    ],
    [
        'name'   => 'Pages',
        'fields' => [
            ['id' => 'path', 'label' => 'Path', 'type' => 'text'],
            ['id' => 'hyper_html', 'label' => 'HTML', 'type' => 'textarea', 'className' => 'hyper-code-field', 'data' => ['language' => 'html']],
            ['id' => 'hyper_css', 'label' => 'CSS', 'type' => 'textarea', 'className' => 'hyper-code-field', 'data' => ['language' => 'css']],
            ['id' => 'hyper_component_elements', 'label' => 'Components', 'type' => 'textarea', 'className' => 'hyper-code-field', 'data' => ['language' => 'json']],
            ['id' => 'hyper_page_project_data', 'label' => 'Project', 'type' => 'textarea', 'className' => 'hyper-code-field', 'data' => ['language' => 'json']],
        ],
        'entries' => [
            [
                'path' => '/about',
                'hyper_html' => '<img src="' . $serve('uploads/upgrade-test/hero.jpg') . '"><p>About</p>',
                'hyper_css' => 'p { margin: 0; }',
                'hyper_component_elements' => '[]',
                'hyper_page_project_data' => '{"assets":[{"src":"' . $escaped($serve('uploads/upgrade-test/hero.jpg')) . '","type":"image"}],"pages":[]}',
            ],
        ],
    ],
    [
        'name'        => 'Guarded',
        'fields'      => [['id' => 'note', 'label' => 'Note', 'type' => 'text']],
        'user_groups' => ['developer', 'admin'],
        'entries'     => [['note' => 'Only for staff']],
    ],
];
