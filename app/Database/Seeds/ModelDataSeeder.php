<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

class ModelDataSeeder extends Seeder
{
    public function run()
    {
        // Truncate the model_data table before seeding
        $this->db->table('model_data')->truncate();

        $faker = Factory::create('en_US');
        $models = $this->db->table('models')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($models as $model) {
            $fields = $this->generateModelFields($faker);

            $data = [
                'model_id' => $model['id'],
                'name' => $faker->unique()->word,
                'fields' => json_encode($fields),
                'creator_id' => $faker->randomElement([1, 2]),
                'deleter_id' => null,
                'created_at' => $model['created_at'], // Same as model's created_at
                'updated_at' => $model['updated_at'], // Same as model's updated_at
                'deleted_at' => null,
            ];

            $this->db->table('model_data')->insert($data);
        }
    }

    private function generateModelFields($faker)
    {
        return [
            [
                'type' => 'field',
                'content' => [
                    'id' => $faker->slug,
                    'nama' => $faker->word,
                    'tipe' => 'text',
                    'required' => true,
                    'value' => $faker->sentence,
                ],
            ],
            [
                'type' => 'field',
                'content' => [
                    'id' => $faker->slug,
                    'nama' => $faker->word,
                    'tipe' => 'editor',
                    'required' => true,
                    'value' => $faker->paragraph,
                ],
            ],
            // Add more field types as needed
        ];
    }
}
