<?php

namespace App\Database\Seeds;

use App\Models\EntriesModel;
use App\Models\EntryDataModel;
use CodeIgniter\Database\Seeder;

class EntriesSeeder extends Seeder
{
    public function run()
    {
        // Instantiate the models.
        $modelsModel   = new EntriesModel();
        $modelDataModel = new EntryDataModel();

        // Optionally, truncate the tables first.
        $modelsModel->truncate();
        $modelDataModel->truncate();

        // Define a start date from which to generate random dates.
        $startTimestamp = strtotime('2020-01-01');
        $nowTimestamp   = time();

        for ($i = 1; $i <= 100; $i++) {
            // Generate random created_at and updated_at for the models table.
            $createdTimestampModels = mt_rand($startTimestamp, $nowTimestamp);
            // Ensure updated_at is not earlier than created_at.
            $updatedTimestampModels = mt_rand($createdTimestampModels, $nowTimestamp);

            $modelCreatedAt = date('Y-m-d H:i:s', $createdTimestampModels);
            $modelUpdatedAt = date('Y-m-d H:i:s', $updatedTimestampModels);

            $modelItem = [
                'model_id'  => $i,
                'creator_id'  => '1',
                'created_at'  => $modelCreatedAt,
                'updated_at'  => $modelUpdatedAt,
                // 'deleted_at'  => $modelUpdatedAt,
            ];

            // Generate random created_at and updated_at for the model_data table.
            $createdTimestampData = mt_rand($startTimestamp, $nowTimestamp);
            $updatedTimestampData = mt_rand($createdTimestampData, $nowTimestamp);

            $modelDataCreatedAt = date('Y-m-d H:i:s', $createdTimestampData);
            $modelDataUpdatedAt = date('Y-m-d H:i:s', $updatedTimestampData);

            $modelDataItem = [
                'entry_id'    => $i,
                'fields'      => json_encode([
                    'description' => 'Value for entry ' . $i,
                    'value'       => rand(1, 100)
                ]),
                'creator_id'  => '1',
                'created_at'  => $modelDataCreatedAt,
                'updated_at'  => $modelDataUpdatedAt,
                // 'deleted_at'  => $modelDataUpdatedAt,
            ];

            // Insert the records.
            $modelsModel->insert($modelItem);
            $modelDataModel->insert($modelDataItem);
        }
    }
}
