<?php
declare(strict_types=1);

// The tables the admin write flows watch. Each key names a table in the capture, and each value is
// the query that reads it. A phase that changes the tables swaps this file for its own and passes
// that path to admin_harness.php as the fifth argument (check.ps1 -Watch).
//
// Rows are matched by their `id` column. A query without one (a count per group) is matched by its
// first two columns.
return [
    'models'     => 'SELECT id, current_model_data_id, creator_id, deleter_id, deleted_at IS NOT NULL AS deleted FROM models ORDER BY id',
    'model_data' => 'SELECT id, model_id, name, fields, `group`, user_groups, icon, creator_id, deleter_id, deleted_at IS NOT NULL AS deleted FROM model_data ORDER BY id',
    'entries'    => 'SELECT id, model_id, current_entry_data_id, creator_id, deleter_id, deleted_at IS NOT NULL AS deleted FROM entries ORDER BY id',
    'entry_data' => 'SELECT id, entry_id, fields, creator_id, deleter_id, deleted_at IS NOT NULL AS deleted FROM entry_data ORDER BY id',
    'queue_jobs' => 'SELECT queue, status, COUNT(*) AS n FROM queue_jobs GROUP BY queue, status ORDER BY queue, status',
    'settings'   => 'SELECT id, class, `key`, value, type, context FROM settings ORDER BY id',
];
