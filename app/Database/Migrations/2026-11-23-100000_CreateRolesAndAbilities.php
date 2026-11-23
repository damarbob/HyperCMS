<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Roles and what they may do, kept in the database so an admin can change them.
 *
 * - `hyper_roles` holds the roles. A person's roles stay in Shield's `auth_groups_users`, which stores the role name as
 *   text, so the name of a role never changes and its label may.
 * - `hyper_abilities` holds the abilities that an admin made. Core and modules register theirs in code.
 * - `hyper_role_abilities` says which role has which global ability.
 * - `hyper_model_permissions` says which role may do which action on which model.
 *
 * The five roles of the config are added with the abilities their groups have today, so nobody's access changes.
 */
class CreateRolesAndAbilities extends Migration
{
    /**
     * The roles of today and what each one may do besides what its model permissions say. A superadmin may do everything
     * and needs no row.
     */
    private const ROLES = [
        'superadmin' => ['Super Admin', 'Complete control of the site.', true, []],
        'admin'      => ['Admin', 'Day to day administrators of the site.', false, ['entries.browse', 'api.access', 'media.manage']],
        'developer'  => ['Developer', 'Site programmers.', false, ['entries.browse', 'api.access', 'media.manage', 'pages.manage']],
        'user'       => ['User', 'General users of the site. Often customers.', false, []],
        'beta'       => ['Beta User', 'Has access to beta-level features.', false, ['media.manage']],
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('hyper_roles')) {
            $this->forge->addField([
                'name'        => ['type' => 'VARCHAR', 'constraint' => 64],
                'label'       => ['type' => 'VARCHAR', 'constraint' => 255],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'locked'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('name');
            $this->forge->createTable('hyper_roles');
        }

        if (! $this->db->tableExists('hyper_abilities')) {
            $this->forge->addField([
                'name'        => ['type' => 'VARCHAR', 'constraint' => 128],
                'label'       => ['type' => 'VARCHAR', 'constraint' => 255],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('name');
            $this->forge->createTable('hyper_abilities');
        }

        if (! $this->db->tableExists('hyper_role_abilities')) {
            $this->forge->addField([
                'role'    => ['type' => 'VARCHAR', 'constraint' => 64],
                'ability' => ['type' => 'VARCHAR', 'constraint' => 128],
            ]);
            $this->forge->addPrimaryKey(['role', 'ability']);
            $this->forge->addKey('ability');
            $this->forge->createTable('hyper_role_abilities');
        }

        if (! $this->db->tableExists('hyper_model_permissions')) {
            $this->forge->addField([
                'model_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'role'     => ['type' => 'VARCHAR', 'constraint' => 64],
                'action'   => ['type' => 'VARCHAR', 'constraint' => 32],
            ]);
            $this->forge->addPrimaryKey(['model_id', 'role', 'action']);
            $this->forge->addKey('role');
            $this->forge->createTable('hyper_model_permissions');
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::ROLES as $name => [$label, $description, $locked, $abilities]) {
            // A role that is already there, from an earlier run or one an admin made, is left as it is.
            if ($this->db->table('hyper_roles')->where('name', $name)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('hyper_roles')->insert(['name' => $name, 'label' => $label, 'description' => $description, 'locked' => $locked ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($abilities as $ability) {
                $this->db->table('hyper_role_abilities')->insert(['role' => $name, 'ability' => $ability]);
            }
        }
    }

    public function down(): void
    {
        foreach (['hyper_model_permissions', 'hyper_role_abilities', 'hyper_abilities', 'hyper_roles'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
