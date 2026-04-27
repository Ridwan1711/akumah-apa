<?php

namespace Database\Seeders;

use App\Models\Diniyyah\GradeLevel;
use Illuminate\Database\Seeder;

class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Ula', 'order' => 1],
            ['name' => 'Wustho', 'order' => 2],
            ['name' => 'Ulya', 'order' => 3],
        ];

        foreach ($rows as $row) {
            GradeLevel::query()->updateOrCreate(
                ['order' => $row['order']],
                ['name' => $row['name']]
            );
        }
    }
}
