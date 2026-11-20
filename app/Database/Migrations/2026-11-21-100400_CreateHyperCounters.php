<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Named counters. The global write version lives here, and it rises whenever something a list
 * shows changes, so a saved list can tell that it is stale.
 */
class CreateHyperCounters extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'name'  => ['type' => 'VARCHAR', 'constraint' => 64],
            'value' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable('hyper_counters', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('hyper_counters', true);
    }
}
