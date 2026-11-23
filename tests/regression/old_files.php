<?php
declare(strict_types=1);

// The files of the old upload folder that the shapes in old_shapes.php point at, by their path below the public folder.
// The upgrade test writes them before the upgrade and removes them after, so the links have files to be moved.

return [
    'uploads/upgrade-test/rich-1.png'    => 'rich image',
    'uploads/upgrade-test/logo.svg'      => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    'uploads/upgrade-test/photo-one.png' => 'photo one',
    'uploads/upgrade-test/photo-two.png' => 'photo two',
    'uploads/upgrade-test/hero.jpg'      => 'hero image',
    'uploads/upgrade-test/loose.txt'     => 'a file that nothing points at',
];
