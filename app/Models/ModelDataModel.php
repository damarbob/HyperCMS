<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class ModelDataModel extends Model
{
    protected $table = 'model_data'; // The table name
    protected $primaryKey = 'id'; // Primary key of the table
    protected $allowedFields = ['model_id', 'name', 'fields', 'icon', 'creator_id', 'deleter_id', 'created_at', 'updated_at', 'deleted_at']; // Fields that can be inserted/updated
    protected $returnType = 'array'; // Return results as arrays
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
}
