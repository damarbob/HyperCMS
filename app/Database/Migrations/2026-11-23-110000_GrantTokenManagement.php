<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets the roles that may use the API make their own access tokens, now that tokens have a screen of their own. Before,
 * anyone holding a token could ask for another one outside production. A role that an admin made, or one that has no
 * access to the API, gets nothing.
 */
class GrantTokenManagement extends Migration
{
    public function up(): void
    {
        $this->db->query('INSERT IGNORE INTO hyper_role_abilities (role, ability) SELECT role, ? FROM hyper_role_abilities WHERE ability = ? AND role IN (?, ?)', ['tokens.manage', 'api.access', 'admin', 'developer']);
    }

    public function down(): void
    {
        $this->db->table('hyper_role_abilities')->where('ability', 'tokens.manage')->delete();
    }
}
