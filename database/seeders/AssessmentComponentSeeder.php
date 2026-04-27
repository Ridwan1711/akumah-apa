<?php

namespace Database\Seeders;

use App\Models\Diniyyah\AssessmentComponent;
use Illuminate\Database\Seeder;

class AssessmentComponentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Harian 1', 'type' => AssessmentComponent::TYPE_DAILY, 'weight' => 33],
            ['name' => 'Harian 2', 'type' => AssessmentComponent::TYPE_DAILY, 'weight' => 33],
            ['name' => 'Evaluasi', 'type' => AssessmentComponent::TYPE_EXAM, 'weight' => 34],
        ];

        foreach ($rows as $row) {
            AssessmentComponent::query()->updateOrCreate(
                ['name' => $row['name'], 'type' => $row['type']],
                ['weight' => $row['weight']]
            );
        }
    }
}
