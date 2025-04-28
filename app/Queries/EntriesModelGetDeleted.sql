-- File: app/Queries/EntriesModelGet.sql
SELECT
    entries.id,
    model_data.model_id as model_id,
    model_data.name as model_name,
    entry_data.fields,
    users.username AS created_by,
    editors.username AS edited_by,
    entries.created_at,
    entry_data.created_at AS date_modified,
    entry_data.id as data_id,
    deleters.username AS deleted_by,
    entries.deleted_at AS date_deleted
FROM
    entries
    LEFT JOIN (
        SELECT
            ed1.*
        FROM
            entry_data AS ed1
            INNER JOIN (
                SELECT
                    entry_id,
                    MAX(id) AS id
                FROM
                    entry_data
                GROUP BY
                    entry_id
            ) AS ed2 ON ed1.entry_id = ed2.entry_id
            AND ed1.id = ed2.id
        WHERE
            deleted_at IS NOT NULL
    ) AS entry_data ON entries.id = entry_data.entry_id
    LEFT JOIN (
        SELECT
            md1.*
        FROM
            model_data AS md1
            INNER JOIN (
                SELECT
                    model_id,
                    MAX(id) AS id
                FROM
                    model_data
                GROUP BY
                    model_id
            ) AS md2 ON md1.model_id = md2.model_id
            AND md1.id = md2.id
    ) AS model_data ON entries.model_id = model_data.model_id
    LEFT JOIN users ON entries.creator_id = users.id
    LEFT JOIN users AS editors ON entry_data.creator_id = editors.id
    LEFT JOIN users AS deleters ON entries.deleter_id = deleters.id
WHERE
    entries.deleted_at IS NOT NULL