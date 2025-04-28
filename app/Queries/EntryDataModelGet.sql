SELECT
    entries.id as entry_id,
    entry_data.id,
    entry_data.fields,
    model_data.model_id as model_id,
    model_data.name as model_name,
    users.username AS created_by,
    entry_data.created_at AS date_created
FROM
    entry_data
    LEFT JOIN entries ON entry_data.entry_id = entries.id
    LEFT JOIN users ON entry_data.creator_id = users.id
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
                WHERE
                    deleted_at IS NULL
                GROUP BY
                    model_id
            ) AS md2 ON md1.model_id = md2.model_id
            AND md1.id = md2.id
        WHERE
            deleted_at IS NULL
    ) AS model_data ON entries.model_id = model_data.model_id
WHERE
    entry_data.deleted_at IS NULL