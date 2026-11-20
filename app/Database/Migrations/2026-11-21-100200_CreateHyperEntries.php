<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per entry. `id` is the public id, `sd_entry_id` is the StarDust 0.3 entry that holds the
 * current fields (NULL while a create or restore is still in progress), and `payload_bytes` is the
 * size of those fields, written on every save so a list can size its getMany() chunks.
 */
class CreateHyperEntries extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'model_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'sd_entry_id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'current_revision_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'creator_id'          => ['type' => 'INT', 'constraint' => 11],
            'editor_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'deleter_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at'          => ['type' => 'DATETIME'],
            'updated_at'          => ['type' => 'DATETIME'],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            'payload_bytes'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sd_entry_id');
        $this->forge->addKey(['model_id', 'deleted_at', 'id']);
        $this->forge->addKey(['deleted_at', 'updated_at']);
        $this->forge->addKey('creator_id');
        $this->forge->createTable('hyper_entries', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('hyper_entries', true);
    }
}
