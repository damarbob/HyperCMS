<?php

namespace App\Libraries;

use App\Hyper\HyperHook;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class SyntaxProcessor
{
    /**
     * @var BaseConnection
     */
    protected BaseConnection $db;

    /**
     * SyntaxProcessor constructor.
     */
    public function __construct()
    {
        // Dependency injection: You could allow injecting a DB connection here.
        $this->db = Database::connect();
    }

    /**
     * Processes JSON content and recursively replaces custom data queries.
     *
     * @param string $content JSON string to process.
     *
     * @return string JSON encoded result. In case of JSON error, returns JSON with an error key.
     */
    public function process(string $content): string
    {
        $jsonData = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$jsonData) {
            return json_encode(['error' => 'Invalid JSON syntax: ' . $content]);
        }

        // Recursively process the JSON to resolve data queries.
        $processedData = $this->processJsonRecursively($jsonData);
        return json_encode($processedData);
    }

    /**
     * Recursively process data arrays and objects.
     *
     * @param mixed $data The input data. Typically an array.
     *
     * @return mixed Processed data.
     */
    protected function processJsonRecursively($data)
    {
        // Process arrays recursively.
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Process custom "data" queries.
                if (
                    is_array($value) &&
                    isset($value['type'], $value['content']) &&
                    $value['type'] === 'data'
                ) {
                    if (is_array($value['content'])) {
                        $data[$key] = $this->fetchDataFromDatabase($value['content']);
                    } elseif ($value['content'] === 'hooks') {
                        $data[$key] = isset($value['group'])
                            ? $this->dumpHooks($value['group'])
                            : $this->dumpHooks();
                    }
                } elseif (is_array($value) || is_object($value)) {
                    $data[$key] = $this->processJsonRecursively($value);
                }
            }
        } elseif (is_object($data)) {
            // Currently no custom object handling is defined.
            // Optionally, implement processing for objects if required.
        }
        return $data;
    }

    /**
     * Dumps hooks from the system (filtered by group if provided).
     *
     * @param string|null $group Optional hook group.
     *
     * @return array List of hooks with their name and label.
     */
    protected function dumpHooks(?string $group = null): array
    {
        $hooks = [];
        foreach (dump_hooks($group) as $hookGroup) {
            foreach ($hookGroup as $hook) {
                if (!$hook instanceof HyperHook) {
                    continue;
                }
                $hooks[] = [
                    'value' => $hook->getName(),
                    'label' => $hook->getLabel(),
                ];
            }
        }
        return $hooks;
    }

    /**
     * Executes a custom query or builds a query based on query parameters.
     *
     * Returns an array of data results or an integer count if the query specifies counting.
     *
     * @param array $queryParams Criteria and options for the query.
     *
     * @return array|int Returns array of sanitized data or a count number.
     */
    protected function fetchDataFromDatabase(array $queryParams): array|int
    {
        // Flag indicating whether we're executing a count query.
        $isCountQuery = !empty($queryParams['count']) && $queryParams['count'] === true;

        // Process raw SQL if provided.
        if (!empty($queryParams['query'])) {
            $rawQuery = trim($queryParams['query']);
            if (stripos($rawQuery, 'SELECT') !== 0) {
                return ['error' => 'Only SELECT queries are allowed for raw SQL.'];
            }
            $query = $this->db->query($rawQuery);
            $result = $query->getResultArray();
            return $this->sanitizeData($result);
        }

        // Validate table: disallow empty or forbidden tables.
        if (
            empty($queryParams['table']) ||
            in_array($queryParams['table'], ['auth_identities', 'users'])
        ) {
            return ['error' => 'Table name not specified/not allowed'];
        }

        $table = $queryParams['table'];
        $builder = $this->db->table($table);

        // For certain tables, use a custom builder from models.
        if ($table === 'models') {
            /** @var \App\Models\ModelsModel */
            $model = model('ModelsModel');
            $builder = $model->getCustomBuilder();
        } elseif ($table === 'entries') {
            /** @var \App\Models\EntriesModel */
            $model = model('EntriesModel');
            $builder = $model->getCustomBuilder();
        }

        // Process select clause with placeholders.
        if (!empty($queryParams['select'])) {
            $selectClause = $this->processClausePlaceholders($queryParams['select']);
            $builder->select($selectClause);
        } else {
            $builder->select('*');
        }

        // Apply WHERE conditions.
        if (!empty($queryParams['where'])) {
            if (is_array($queryParams['where'])) {
                foreach ($queryParams['where'] as $condition) {
                    if (
                        is_array($condition) &&
                        isset($condition['column'], $condition['operator'], $condition['value'])
                    ) {
                        $builder->where($condition['column'] . ' ' . $condition['operator'], $condition['value']);
                    } else {
                        $builder->where($condition);
                    }
                }
            } else {
                $builder->where($queryParams['where']);
            }
        }

        // Apply LIKE clauses.
        if (!empty($queryParams['like']) && is_array($queryParams['like'])) {
            foreach ($queryParams['like'] as $field => $value) {
                $builder->like($field, $value);
            }
        }

        // Apply JOINs.
        if (!empty($queryParams['joins']) && is_array($queryParams['joins'])) {
            foreach ($queryParams['joins'] as $join) {
                if (isset($join['table'], $join['condition'], $join['type'])) {
                    $builder->join($join['table'], $join['condition'], $join['type']);
                }
            }
        }

        // GROUP BY.
        if (!empty($queryParams['groupby'])) {
            $builder->groupBy($queryParams['groupby']);
        }

        // HAVING conditions.
        if (!empty($queryParams['having']) && is_array($queryParams['having'])) {
            foreach ($queryParams['having'] as $condition) {
                if (is_array($condition) && isset($condition['column'], $condition['operator'], $condition['value'])) {
                    $builder->having($condition['column'] . ' ' . $condition['operator'], $condition['value']);
                } else {
                    $builder->having($condition);
                }
            }
        }

        // ORDER BY.
        if (!empty($queryParams['orderby'])) {
            $builder->orderBy($this->processClausePlaceholders($queryParams['orderby']), escape: false);
        }

        // LIMIT and OFFSET.
        if (!empty($queryParams['limit'])) {
            $limit = (int) $queryParams['limit'];
            $offset = !empty($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
            $builder->limit($limit, $offset);
        }

        // dd($builder->getCompiledSelect()); // To debug the SQL query.

        // Execute query.
        if (!$isCountQuery) {
            $result = $builder->get()->getResultArray();
            return $this->sanitizeData($result);
        } else {
            return $builder->countAllResults();
        }
    }

    /**
     * Processes a generic SQL clause by replacing two types of field placeholders:
     *   - {{field:FIELD_ID}}: Replaced with the casted field value expression.
     *   - {{model_field:FIELD_ID.ATTRIBUTE}}: Replaced with the attribute extraction expression
     *     from the model_fields JSON column.
     *
     * @param string $clause The raw SQL clause containing placeholders.
     * @return string The processed clause with all placeholders replaced.
     */
    private function processClausePlaceholders(string $clause): string
    {
        // Process placeholders for field values: e.g. {{field:FIELD_ID}}
        $patternField = '/\{\{field:([^}]+)\}\}/';
        if (preg_match_all($patternField, $clause, $matchesField)) {
            foreach ($matchesField[0] as $index => $placeholder) {
                $fieldId = trim($matchesField[1][$index]);
                // Replace with a casted field value extraction expression.
                $replacement = $this->getCastedFieldValueExpression($fieldId);
                $clause = str_replace($placeholder, $replacement, $clause);
            }
        }

        // Process placeholders for model field attributes: e.g. {{model_field:FIELD_ID.ATTRIBUTE}}
        $patternModelField = '/\{\{model_field:([^}]+)\}\}/';
        if (preg_match_all($patternModelField, $clause, $matchesModel)) {
            foreach ($matchesModel[0] as $index => $placeholder) {
                $content = trim($matchesModel[1][$index]); // expected format: FIELD_ID.ATTRIBUTE
                if (strpos($content, '.') !== false) {
                    list($fieldId, $attribute) = explode('.', $content, 2);
                    $replacement = $this->getModelFieldsJsonAttrExpression($fieldId, $attribute);
                } else {
                    // If no attribute is provided, you may choose a default behavior.
                    // For instance, you can return an empty string or the whole field configuration.
                    $replacement = $this->getModelFieldsJsonAttrExpression($content, '');
                }
                $clause = str_replace($placeholder, $replacement, $clause);
            }
        }

        return $clause;
    }

    /**
     * Generates a SQL expression to extract a field's value from the `fields` JSON column
     * and cast it appropriately based on the field's type (obtained from the `model_fields` JSON column).
     *
     * The function uses a CASE statement on the field type (which is extracted using getModelFieldsJsonAttrExpression)
     * and then casts the value from the `fields` column accordingly.
     *
     * Example mapping:
     * - If the type is 'number' or 'range', the value is cast as DECIMAL(10,2).
     * - If the type is 'date', the value is converted via STR_TO_DATE(..., '%Y-%m-%d').
     * - If the type is 'datetime' or 'datetime-local', it's converted with a date‑time format.
     * - For any other type, the value is left unaltered (i.e. as text).
     *
     * @param string $fieldId The identifier for the field.
     *
     * @return string The SQL expression to obtain the cast field value.
     */
    private function getCastedFieldValueExpression(string $fieldId): string
    {
        // Expression to extract the field's type from the `model_fields` JSON column.
        $fieldTypeExpr = $this->getModelFieldsJsonAttrExpression($fieldId, 'type');

        // Expression to extract the field's value from the `fields` JSON column.
        $fieldValueExpr = $this->getFieldsJsonValueExpression($fieldId);

        // Build the CASE statement to cast the field value based on its type.
        $caseExpression = "(CASE 
            WHEN $fieldTypeExpr IN ('number', 'range') THEN CAST($fieldValueExpr AS DECIMAL(10,2))
            WHEN $fieldTypeExpr = 'date' THEN STR_TO_DATE($fieldValueExpr, '%Y-%m-%d')
            WHEN $fieldTypeExpr IN ('datetime', 'datetime-local') THEN STR_TO_DATE($fieldValueExpr, '%Y-%m-%d %H:%i:%s')
            WHEN $fieldTypeExpr = 'time' THEN STR_TO_DATE($fieldValueExpr, '%H:%i:%s')
            ELSE $fieldValueExpr
        END)";

        return "(COALESCE($caseExpression, $fieldValueExpr))";
    }

    /**
     * Generates SQL expression to extract a field's value from the `fields` JSON column.
     */
    private function getFieldsJsonValueExpression(string $fieldId): string
    {
        $query = "(JSON_UNQUOTE(JSON_EXTRACT(fields, REPLACE(JSON_UNQUOTE(JSON_SEARCH(fields, 'one', '$fieldId', NULL, '\$[*].id')), '.id', '.value'))))";
        return $query;
    }

    /**
     * Generates SQL expression to extract a field's attribute from the `model_fields` JSON column.
     */
    private function getModelFieldsJsonAttrExpression(string $fieldId, string $attr): string
    {
        return "(JSON_UNQUOTE(JSON_EXTRACT(model_fields, REPLACE(JSON_UNQUOTE(JSON_SEARCH(model_fields, 'one', '$fieldId', NULL, '\$[*].id')), '.id', '.$attr'))))";
    }

    /**
     * Sanitizes an array of data for safe JSON encoding, escaping HTML entities and removing newlines.
     *
     * @param array $data Raw data.
     *
     * @return array Sanitized data.
     */
    protected function sanitizeData(array $data): array
    {
        return array_map(function ($item) {
            return array_map(function ($value) {
                // Escape HTML entities.
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                // Replace new line characters with a space.
                return str_replace(["\r\n", "\r", "\n"], ' ', $value);
            }, $item);
        }, $data);
    }
}
