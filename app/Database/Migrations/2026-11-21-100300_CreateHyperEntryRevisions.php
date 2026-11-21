<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per saved version of an entry. `fields` is filled only while that version is not the
 * live one in StarDust 0.3, so the current payload exists in exactly one place. It is LONGTEXT
 * because bodies of 1 to 3 MB exist, and it carries no JSON check because MySQL and MariaDB
 * disagree on the syntax.
 */
class CreateHyperEntryRevisions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'entry_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'fields'     => ['type' => 'LONGTEXT', 'null' => true],
            'creator_id' => ['type' => 'INT', 'constraint' => 11],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entry_id', 'id']);
        $this->forge->createTable('hyper_entry_revisions', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('hyper_entry_revisions', true);
    }
}
