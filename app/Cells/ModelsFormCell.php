<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class ModelsFormCell extends Cell
{
    public $action = ''; // Form action (new/edit)
    public $formAction = ''; // Form action url
    public $model; // Current model (required by action 'edit')
}
