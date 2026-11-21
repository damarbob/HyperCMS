<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per model. `id` is the public id that links, settings and select values hold, and
 * `sd_model_id` is the StarDust 0.3 model registered for it. The form definition stays here in
 * full, because 0.3 keeps only field names and four stored types.
 */
class CreateHyperModels extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sd_model_id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'name'                => ['type' => 'VARCHAR', 'constraint' => 255],
            'fields'              => ['type' => 'LONGTEXT', 'null' => true],
            'group'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'icon'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_groups'         => ['type' => 'TEXT', 'null' => true],
            'current_revision_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'creator_id'          => ['type' => 'INT', 'constraint' => 11],
            'editor_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'deleter_id'          => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at'          => ['type' => 'DATETIME'],
            'updated_at'          => ['type' => 'DATETIME'],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            'write_version'       => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sd_model_id');
        $this->forge->addKey(['deleted_at', 'id']);
        $this->forge->createTable('hyper_models', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('hyper_models', true);
    }
}
