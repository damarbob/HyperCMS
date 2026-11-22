<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The id of the field that names an entry of a model, such as `title` for an article. Lists and pickers show that
 * field instead of the id of the entry. It is empty until the model builder or the upgrade sets it.
 */
class AddLabelFieldToHyperModels extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('label_field', 'hyper_models')) {
            return;
        }

        $this->forge->addColumn('hyper_models', [
            'label_field' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'fields'],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('label_field', 'hyper_models')) {
            $this->forge->dropColumn('hyper_models', 'label_field');
        }
    }
}
