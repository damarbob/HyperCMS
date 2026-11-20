<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per saved version of a model, each holding the whole definition.
 */
class CreateHyperModelRevisions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'model_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'fields'      => ['type' => 'LONGTEXT', 'null' => true],
            'group'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'icon'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'user_groups' => ['type' => 'TEXT', 'null' => true],
            'creator_id'  => ['type' => 'INT', 'constraint' => 11],
            'created_at'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['model_id', 'id']);
        $this->forge->createTable('hyper_model_revisions', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_general_ci',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('hyper_model_revisions', true);
    }
}
