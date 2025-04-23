<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

class EntriesFormCell extends Cell {
    public $type = ''; // Form type (new/edit)
    public $entry; // Current entry (required by type 'edit')
    public $model; // Current model (required by type 'new')
}