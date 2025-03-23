<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class ModelsModel extends Model
{
    protected $table = 'models';           // The table name
    protected $primaryKey = 'id';          // Primary key of the table
    protected $allowedFields = ['creator_id', 'deleter_id', 'created_at', 'updated_at']; // Fields that can be inserted/updated
    protected $returnType = 'array';       // Return results as arrays
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;

    function test()
    {
        $tables = $this->db->listTables();
        // dd($tables);
    }

    /**
     * Load the SQL query from an external file and return a BaseBuilder.
     *
     * @return BaseBuilder
     * @throws \Exception if the SQL file is not found.
     */
    public function getCustomBuilder(): BaseBuilder
    {
        // Define the path to your SQL file.
        $filepath = APPPATH . 'Queries/ModelsModelGet.sql';

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
}
