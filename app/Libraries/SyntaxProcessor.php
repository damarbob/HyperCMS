<?php

namespace App\Libraries;

use App\Entities\HyperHook;
use App\Models\EntriesModel;
use App\Models\ModelsModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class SyntaxProcessor
{

    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();  // Load the CI4 database connection
    }

    // 
    public function process($content)
    {
        $jsonData = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$jsonData) {
            return json_encode(['error' => 'Invalid JSON syntax: ' . $content]);
        }

        // Recursively process the JSON to find and replace data queries
        $processedData = $this->processJsonRecursively($jsonData);

        return json_encode($processedData);
    }

    protected function processJsonRecursively($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Check if the value is an array and contains the data syntax for data fetching
                if (is_array($value) && isset($value['type']) && $value['type'] === 'data' && isset($value['content'])) {
                    // If the content is array, fetch data from the database
                    if (is_array($value['content'])) {
                        $data[$key] = $this->fetchDataFromDatabase($value['content']);
                    }
                    // If the content is 'hooks', dump the hooks
                    else if ($value['content'] === 'hooks') {
                        if (isset($value['group'])) {
                            $data[$key] = $this->dumpHooks($value['group']);
                        } else {
                            // If no group is specified, dump all hooks
                            $data[$key] = $this->dumpHooks();
                        }
                    }
                } elseif (is_array($value) || is_object($value)) {
                    $data[$key] = $this->processJsonRecursively($value);
                }
            }
        } elseif (is_object($data)) {
        }
        return $data;
    }

    protected function dumpHooks($group = null)
    {
        $hooks = [];

        foreach (dump_hooks($group) as $hookGroup) {
            foreach ($hookGroup as $hook) {
                if (!$hook instanceof HyperHook) {
                    continue; // Skip if not an instance of HyperHook
                }
                $hooks[] = [
                    'value' => $hook->getName(),
                    'label' => $hook->getLabel(),
                ];
            }
        }
        return $hooks;
    }

    // Function to execute the query and fetch data from the database based on the custom syntax
    protected function fetchDataFromDatabase($queryParams)
    {
        $count = false; // Initialize count variable

        if (isset($queryParams['query'])) {

            $rawQuery = trim($queryParams['query']);

            // Ensure the query starts with 'SELECT'
            if (stripos($rawQuery, 'SELECT') !== 0) {
                return ['error' => 'Only SELECT queries are allowed for raw SQL.'];
            }

            // Execute the provided raw SQL query
            $query = $this->db->query($rawQuery);
            $result = $query->getResultArray();

            return $this->sanitizeData($result);
        }

        // If table is empty or forbidden tables
        if (!isset($queryParams['table']) || $queryParams['table'] === 'auth_identities' || $queryParams['table'] === 'users') {
            return ['error' => 'Table name not specified/not allowed'];
        }

        // Get the table name
        $table = $queryParams['table'];

        // Start building the query
        $builder = $this->db->table($table);

        if ($table === 'models') {
            $model = new ModelsModel();
            $builder = $model->getCustomBuilder();
        } elseif ($table === 'entries') {
            $model = new EntriesModel();
            $builder = $model->getCustomBuilder();
        }

        // Apply the SELECT clause if present, otherwise select all (*)
        if (isset($queryParams['select']) && !empty($queryParams['select'])) {
            $builder->select($queryParams['select']);
        } else {
            $builder->select('*');
        }

        // Apply WHERE conditions (supports complex conditions with arrays)
        if (isset($queryParams['where'])) {
            if (is_array($queryParams['where'])) {
                foreach ($queryParams['where'] as $condition) {
                    if (is_array($condition) && isset($condition['column'], $condition['operator'], $condition['value'])) {
                        $builder->where($condition['column'] . ' ' . $condition['operator'], $condition['value']);
                    } else {
                        // Handle standard where key-value pairs
                        $builder->where($condition);
                    }
                }
            } else {
                // Handle standard where query
                $builder->where($queryParams['where']);
            }
        }

        // Apply LIKE conditions (supports complex LIKE clauses)
        if (isset($queryParams['like']) && is_array($queryParams['like'])) {
            foreach ($queryParams['like'] as $field => $value) {
                $builder->like($field, $value);
            }
        }

        // Apply JOINs if specified (supports multiple joins)
        if (isset($queryParams['joins']) && is_array($queryParams['joins'])) {
            foreach ($queryParams['joins'] as $join) {
                if (isset($join['table'], $join['condition'], $join['type'])) {
                    $builder->join($join['table'], $join['condition'], $join['type']);
                }
            }
        }

        // Apply GROUP BY if specified
        if (isset($queryParams['groupby'])) {
            $builder->groupBy($queryParams['groupby']);
        }

        // Apply HAVING if specified (similar to WHERE but for grouped data)
        if (isset($queryParams['having']) && is_array($queryParams['having'])) {
            foreach ($queryParams['having'] as $condition) {
                if (is_array($condition) && isset($condition['column'], $condition['operator'], $condition['value'])) {
                    $builder->having($condition['column'] . ' ' . $condition['operator'], $condition['value']);
                } else {
                    $builder->having($condition);
                }
            }
        }

        // Apply ORDER BY if specified
        if (isset($queryParams['orderby'])) {
            $builder->orderBy($queryParams['orderby']);
        }

        // Apply LIMIT and OFFSET for pagination
        if (isset($queryParams['limit'])) {
            $limit = $queryParams['limit'];
            $offset = isset($queryParams['offset']) ? $queryParams['offset'] : 0;
            $builder->limit(intval($limit), $offset); // Intval limit to prevent invalid argument due to different data type
        }

        // Check if "count" is requested
        if (isset($queryParams['count']) && $queryParams['count'] === true) {
            $count = true;
        }

        if (!$count) {
            // Execute the query and get the result
            $result = $builder->get()->getResultArray();

            return $this->sanitizeData($result);
        } else {
            // Mode counting
            return $builder->countAllResults();
        }
    }

    // Function to sanitize data before JSON encoding
    protected function sanitizeData($data)
    {
        return array_map(function ($item) {
            return array_map(function ($value) {
                // Escape HTML entities
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

                // Handle new lines and other special characters
                return str_replace(["\r\n", "\r", "\n"], ' ', $value); // Replace new line characters with a space
            }, $item);
        }, $data);
    }
}
