<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHyperTables extends Migration
{
    public function up()
    {
        // ===============================================
        // Create "entries" table
        // ===============================================
        $this->forge->addField([
            'id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'auto_increment'  => true,
            ],
            'model_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            'creator_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            'deleter_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'null'            => true,
            ],
            'created_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'updated_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'deleted_at' => [
                'type'            => 'DATETIME',
                'null'            => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $attributesEntries = [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb3',
            'COLLATE' => 'utf8mb3_general_ci',
        ];
        $this->forge->createTable('entries', true, $attributesEntries);

        // ===============================================
        // Create "entry_data" table
        // ===============================================
        $this->forge->addField([
            'id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'auto_increment'  => true,
            ],
            'entry_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            // We initially create "fields" as LONGTEXT. We'll update it below with the proper
            // character set/collation and a CHECK constraint.
            'fields' => [
                'type'            => 'LONGTEXT',
                'null'            => false,
            ],
            'creator_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            'deleter_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'null'            => true,
            ],
            'created_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'updated_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'deleted_at' => [
                'type'            => 'DATETIME',
                'null'            => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $attributesEntryData = [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb3',
            'COLLATE' => 'utf8mb3_general_ci',
        ];
        $this->forge->createTable('entry_data', true, $attributesEntryData);

        // ===============================================
        // Create "models" table
        // ===============================================
        $this->forge->addField([
            'id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'auto_increment'  => true,
            ],
            'creator_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            'deleter_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'null'            => true,
            ],
            'created_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'updated_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'deleted_at' => [
                'type'            => 'DATETIME',
                'null'            => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $attributesModels = [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb3',
            'COLLATE' => 'utf8mb3_general_ci',
        ];
        $this->forge->createTable('models', true, $attributesModels);

        // ===============================================
        // Create "model_data" table
        // ===============================================
        $this->forge->addField([
            'id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'auto_increment'  => true,
            ],
            'model_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            'name' => [
                'type'            => 'VARCHAR',
                'constraint'      => 255,
            ],
            // Create "fields" column initially
            'fields' => [
                'type'            => 'LONGTEXT',
                'null'            => false,
            ],
            'group' => [
                'type'            => 'TEXT',
                'null'            => true,
            ],
            'user_groups' => [
                'type'            => 'TEXT',
                'null'            => true,
            ],
            'icon' => [
                'type'            => 'TEXT',
                'null'            => true,
            ],
            'creator_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
            ],
            'deleter_id' => [
                'type'            => 'INT',
                'constraint'      => 11,
                'null'            => true,
            ],
            'created_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'updated_at' => [
                'type'            => 'DATETIME',
                'null'            => false,
            ],
            'deleted_at' => [
                'type'            => 'DATETIME',
                'null'            => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $attributesModelData = [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb3',
            'COLLATE' => 'utf8mb3_general_ci',
        ];
        $this->forge->createTable('model_data', true, $attributesModelData);

        // ===============================================
        // Update "fields" columns to add character set/collation and JSON check constraint
        // ===============================================
        $db = \Config\Database::connect();
        // Note: The following ALTER statements assume your MariaDB/MySQL version supports CHECK constraints.
        // Adjust or remove these if your version does not support them.
        $db->query("ALTER TABLE `entry_data` MODIFY `fields` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`))");
        $db->query("ALTER TABLE `model_data` MODIFY `fields` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`))");
    }

    public function down()
    {
        $this->forge->dropTable('model_data', true);
        $this->forge->dropTable('models', true);
        $this->forge->dropTable('entry_data', true);
        $this->forge->dropTable('entries', true);
    }
}
