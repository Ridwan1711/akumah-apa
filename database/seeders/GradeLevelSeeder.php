<?php

namespace Database\Seeders;

use App\Models\Diniyyah\GradeLevel;
use Illuminate\Database\Seeder;

class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Ibtida', 'order' => 1],
            ['name' => '1 Salafy', 'order' => 2],
            ['name' => '2 Salafy', 'order' => 3],
            ['name' => '3 Salafy', 'order' => 4],
            ['name' => '4 Salafy', 'order' => 5],
            ['name' => '5 Salafy', 'order' => 6],
            ['name' => '6 Salafy', 'order' => 7],
            ['name' => '7 Salafy', 'order' => 8],
            ['name' => '8 Salafy', 'order' => 9],
            ['name' => '9 Salafy', 'order' => 10],
        ];

        foreach ($rows as $row) {
            GradeLevel::query()->updateOrCreate(
                ['order' => $row['order']],
                ['name' => $row['name']]
            );
        }
    }
}
