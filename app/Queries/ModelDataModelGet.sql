SELECT DISTINCT
    model_data.id as data_id,
    models.id,
    model_data.name,
    model_data.fields,
    model_data.icon,
    users.username AS created_by,
    model_data.created_at AS date_created
FROM
    model_data
    LEFT JOIN models ON model_data.model_id = models.id
    LEFT JOIN users ON model_data.creator_id = users.id
WHERE
    model_data.deleted_at IS NULL
ORDER BY
    data_id DESC