<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The media library. One row per file, and the file itself sits in the upload folder at `path`, which is relative to
 * that folder and uses `/` between folders. A file is known by its `id`, so renaming or moving it keeps every link.
 */
class CreateHyperFiles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'path'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'        => ['type' => 'VARCHAR', 'constraint' => 127, 'default' => 'application/octet-stream'],
            'size'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 0],
            'uploader_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('path');
        $this->forge->addKey('name');
        $this->forge->createTable('hyper_files', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_bin',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('hyper_files', true);
    }
}
