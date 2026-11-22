<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;

/**
 * A page that shows every part of the design system with sample content, for the people who build screens. It is only
 * registered outside production.
 */
class Design extends AdminController
{
    public function index(): string
    {
        $this->data['title']  = 'Design system';
        $this->data['crumbs'] = [['Build', null], ['Design system', null]];

        return view('admin/design', $this->data);
    }
}
