<?php

namespace Database\Seeders;

use App\Models\ViolationType;
use Illuminate\Database\Seeder;

class ViolationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Terlambat shalat berjamaah', 'points' => 5, 'category' => 'ringan'],
            ['name' => 'Tidak piket kamar', 'points' => 5, 'category' => 'ringan'],
            ['name' => 'Tidak mengikuti halaqah', 'points' => 10, 'category' => 'ringan'],
            ['name' => 'Keluar lingkungan tanpa izin', 'points' => 25, 'category' => 'sedang'],
            ['name' => 'Membawa HP tanpa izin', 'points' => 30, 'category' => 'sedang'],
            ['name' => 'Berkelahi', 'points' => 40, 'category' => 'berat'],
            ['name' => 'Merokok', 'points' => 50, 'category' => 'berat'],
            ['name' => 'Mencuri', 'points' => 75, 'category' => 'berat'],
            ['name' => 'Merusak fasilitas', 'points' => 30, 'category' => 'sedang'],
            ['name' => 'Tidak sopan kepada ustadz', 'points' => 20, 'category' => 'sedang'],
        ];

        foreach ($types as $type) {
            ViolationType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
