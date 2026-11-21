<?php
declare(strict_types=1);

// The tables the admin write flows watch. Each key names a table in the capture, and each value is
// the query that reads it. A phase that changes the tables swaps this file for its own and passes
// that path to admin_harness.php as the fifth argument (check.ps1 -Watch).
//
// Entries and models live in the hyper tables, and the current fields of an entry live in StarDust's
// `sd_entry_data`. The old tables (`models`, `entries` and their versions) are only read by the upgrade
// and no write touches them. Rows are matched by their `id` column. A query without one (a count per
// group) is matched by its first two columns.
return [
    'hyper_models'          => 'SELECT id, sd_model_id, name, current_revision_id, write_version, creator_id, editor_id, deleter_id, deleted_at IS NOT NULL AS deleted FROM hyper_models ORDER BY id',
    'hyper_model_revisions' => 'SELECT id, model_id, name, `fields`, `group`, user_groups, icon, creator_id FROM hyper_model_revisions ORDER BY id',
    'hyper_entries'         => 'SELECT id, model_id, sd_entry_id IS NOT NULL AS in_sd, current_revision_id, payload_bytes, creator_id, editor_id, deleter_id, deleted_at IS NOT NULL AS deleted FROM hyper_entries ORDER BY id',
    'hyper_entry_revisions' => 'SELECT id, entry_id, `fields`, creator_id FROM hyper_entry_revisions ORDER BY id',
    'hyper_counters'        => 'SELECT name AS id, value FROM hyper_counters ORDER BY name',
    'sd_entry_data'         => 'SELECT id, model_id, `fields`, deleted_at IS NOT NULL AS deleted FROM sd_entry_data ORDER BY id',
    'settings'              => 'SELECT id, class, `key`, value, type, context FROM settings ORDER BY id',
];
