<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

class EntriesSeeder extends Seeder
{
    public function run()
    {
        // Truncate
        $this->db->table('entries')->truncate();

        $faker = Factory::create();
        $models = $this->db->table('models')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();
        $entriesCount = 10;

        foreach ($models as $model) {
            for ($i = 0; $i < $entriesCount; $i++) {
                $data = [
                    'model_id' => $model['id'],
                    'creator_id' => $faker->randomElement([1, 2]),
                    'deleter_id' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'deleted_at' => null,
                ];

                $this->db->table('entries')->insert($data);
            }
        }
    }
}
