<?php

namespace App\Database\Seeds;

use App\Models\EntriesModel;
use App\Models\ModelDataModel;
use CodeIgniter\Database\Seeder;
use App\Models\ModelsModel;
use Faker\Factory;

class ModelsSeeder extends Seeder
{
    public function run()
    {
        // Truncate
        $this->db->table('models')->truncate();

        $faker = Factory::create('en_US');
        $modelCount = 2;

        for ($i = 0; $i < $modelCount; $i++) {
            $data = [
                'creator_id' => $faker->randomElement([1, 2]),
                'deleter_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ];

            $this->db->table('models')->insert($data);
        }
    }
}
