-- File: app/Queries/ModelsModelGet.sql
SELECT
    models.id,
    model_data.name,
    model_data.fields,
    model_data.group,
    model_data.user_groups,
    model_data.icon,
    users.username AS created_by,
    editors.username AS edited_by,
    models.created_at,
    model_data.created_at AS date_modified,
    model_data.id as data_id,
    deleters.username AS deleted_by,
    models.deleted_at AS date_deleted
FROM
    models
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
    ) AS model_data ON models.id = model_data.model_id
    LEFT JOIN users ON models.creator_id = users.id
    LEFT JOIN users AS editors ON model_data.creator_id = editors.id
    LEFT JOIN users AS deleters ON models.deleter_id = deleters.id
WHERE
    models.deleted_at IS NULL