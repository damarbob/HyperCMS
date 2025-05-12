<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class EntriesModel extends Model
{
    protected $table = 'entries';           // The table name
    protected $primaryKey = 'id';          // Primary key of the table
    protected $allowedFields = ['model_id', 'creator_id', 'deleter_id', 'created_at', 'updated_at', 'deleted_at']; // Fields that can be inserted/updated
    protected $returnType = 'array';       // Return results as arrays
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;

    /**
     * Load the SQL query from an external file and return a BaseBuilder.
     *
     * @return BaseBuilder
     * @throws \Exception if the SQL file is not found.
     */
    public function getCustomBuilder(): BaseBuilder
    {
        // Define the path to your SQL file.
        $filepath = APPPATH . 'Queries/EntriesModelGet.sql';

        // Check if the file exists.
        if (! file_exists($filepath)) {
            throw new \Exception("SQL file not found: " . $filepath);
        }

        // Read the SQL content.
        $sql = file_get_contents($filepath);

        /*
         * Wrap the loaded SQL as a subquery.
         * The idea is to use the subquery in the FROM clause.
         *
         * Note: Make sure your SQL query at Queries/my_query.sql does not include
         * any trailing semicolon, since it will be embedded as a subquery.
         */
        $builder = $this->db->table("($sql) as sub");

        return $builder;
    }

    /**
     * Load the SQL query from an external file and return a BaseBuilder.
     *
     * @return BaseBuilder
     * @throws \Exception if the SQL file is not found.
     */
    public function getDeletedCustomBuilder(): BaseBuilder
    {
        // Define the path to your SQL file.
        $filepath = APPPATH . 'Queries/EntriesModelGetDeleted.sql';

        // Check if the file exists.
        if (! file_exists($filepath)) {
            throw new \Exception("SQL file not found: " . $filepath);
        }

        // Read the SQL content.
        $sql = file_get_contents($filepath);

        /*
         * Wrap the loaded SQL as a subquery.
         * The idea is to use the subquery in the FROM clause.
         *
         * Note: Make sure your SQL query at Queries/my_query.sql does not include
         * any trailing semicolon, since it will be embedded as a subquery.
         */
        $builder = $this->db->table("($sql) as sub");

        return $builder;
    }

    /**
     * Adds a grouped JSON search condition to a BaseBuilder instance.
     *
     * This method cycles through each condition in the provided array and appends a raw
     * SQL WHERE clause to the query builder. Each condition is expected to have these keys:
     * - 'field': The JSON key within the "fields" column to search.
     * - 'value': The value to match, case-insensitive.
     *
     * The SQL generated extracts a JSON field value from the "fields" column using MySQL's
     * JSON functions. It converts the extracted value to lowercase and then searches for a
     * partial match using LIKE.
     *
     * Example usage:
     * <code>
     *   $builder = $this->myModel->builder();
     *   $builder = $this->whereFields($builder, [
     *       ['field' => 'name', 'value' => 'john'],
     *       ['field' => 'email', 'value' => 'example.com']
     *   ]);
     *   $result = $builder->get()->getResult();
     * </code>
     *
     * @param BaseBuilder $builder The query builder instance.
     * @param array $conditions An array of associative arrays with keys 'field' and 'value'.
     *
     * @return BaseBuilder The modified query builder instance.
     */
    public function whereFields(BaseBuilder $builder, array $conditions): BaseBuilder
    {
        // Start grouping the conditions to keep them logically together.
        $builder->groupStart();

        foreach ($conditions as $condition) {
            // Pre-calculate values for the condition.
            // Ensure the field is properly escaped if coming from user input.
            $conditionField = $condition['field'];
            $conditionValue = strtolower($condition['value']);

            // Construct the SQL condition.
            // This SQL extracts the JSON value from the 'fields' column for the supplied field,
            // converts it to lowercase and applies a LIKE search.
            $sql = <<<SQL
                LOWER(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(
                            fields,
                            CONCAT(
                                '$[',
                                SUBSTRING_INDEX(
                                    SUBSTRING_INDEX(
                                        JSON_SEARCH(fields, 'one', '{$conditionField}', NULL, '$[*].id'),
                                        '[',
                                        -1
                                    ),
                                    ']',
                                    1
                                ),
                                '].value'
                            )
                        )
                    )
                ) LIKE '%{$conditionValue}%'
                SQL;

            // Append the raw SQL condition.
            // Passing null as the second parameter and false for automatic escaping.
            $builder->where($sql, null, false);
        }

        // End the group of conditions.
        $builder->groupEnd();

        // Return the builder instance for further chaining.
        return $builder;
    }
}
