<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

class EntryDataSeeder extends Seeder
{
    public function run()
    {
        // Truncate
        $this->db->table('entry_data')->truncate();

        $faker = Factory::create();
        $entries = $this->db->table('entries')->get()->getResultArray();

        foreach ($entries as $entry) {
            $modelData = $this->db->table('model_data')
                ->where('model_id', $entry['model_id'])
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            $fields = json_decode($modelData['fields'], true);
            $entryFields = $this->generateEntryFields($fields, $faker);

            $data = [
                'entry_id' => $entry['id'],
                'fields' => json_encode($entryFields),
                'creator_id' => $faker->randomElement([1, 2]),
                'deleter_id' => null,
                'created_at' => $entry['created_at'], // Same as entry's created_at
                'updated_at' => $entry['updated_at'], // Same as entry's updated_at
                'deleted_at' => null,
            ];

            $this->db->table('entry_data')->insert($data);
        }
    }

    private function generateEntryFields($fields, $faker)
    {
        $entryFields = [];

        foreach ($fields as $field) {
            $content = $field['content'];
            $entryFields[] = [
                'id' => $content['id'],
                'value' => $this->generateFieldValue($content['tipe'], $faker),
            ];
        }

        return $entryFields;
    }

    private function generateFieldValue($type, $faker)
    {
        switch ($type) {
            case 'text':
                return $faker->sentence;
            case 'editor':
                return $faker->paragraph;
            case 'checkbox':
                return $faker->randomElement(['on', 'off']);
            case 'radio':
                return $faker->randomElement(['male', 'female']);
            case 'datetime-local':
                return $faker->dateTimeThisDecade()->format('Y-m-d H:i:s');
            case 'color':
                return $faker->hexColor;
            case 'range':
                return $faker->numberBetween(0, 100);
            case 'select':
                return $faker->randomElement(['id', 'us', 'uk']);
            case 'textarea':
                return $faker->paragraph;
            default:
                return $faker->word;
        }
    }
}
