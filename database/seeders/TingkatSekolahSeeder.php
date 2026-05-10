<?php

namespace Database\Seeders;

use App\Models\TingkatSekolah;
use Illuminate\Database\Seeder;

class TingkatSekolahSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Kelas 7 (MTs)', 'code' => TingkatSekolah::CODE_MTS_7, 'group' => 'MTs', 'order' => 1, 'is_billable' => true],
            ['name' => 'Kelas 8 (MTs)', 'code' => TingkatSekolah::CODE_MTS_8, 'group' => 'MTs', 'order' => 2, 'is_billable' => true],
            ['name' => 'Kelas 9 (MTs)', 'code' => TingkatSekolah::CODE_MTS_9, 'group' => 'MTs', 'order' => 3, 'is_billable' => true],
            ['name' => 'Kelas 10 (MA)', 'code' => TingkatSekolah::CODE_MA_10, 'group' => 'MA', 'order' => 4, 'is_billable' => true],
            ['name' => 'Kelas 11 (MA)', 'code' => TingkatSekolah::CODE_MA_11, 'group' => 'MA', 'order' => 5, 'is_billable' => true],
            ['name' => 'Kelas 12 (MA)', 'code' => TingkatSekolah::CODE_MA_12, 'group' => 'MA', 'order' => 6, 'is_billable' => true],
            ['name' => 'Kuliah', 'code' => TingkatSekolah::CODE_KULIAH, 'group' => 'Kuliah', 'order' => 7, 'is_billable' => true],
        ];

        foreach ($rows as $row) {
            TingkatSekolah::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'group' => $row['group'],
                    'order' => $row['order'],
                    'is_billable' => $row['is_billable'],
                ]
            );
        }
    }
}
