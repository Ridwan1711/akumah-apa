<?php

namespace Database\Seeders;

use App\Models\Diniyyah\Fan;
use Illuminate\Database\Seeder;

class FanSeeder extends Seeder
{
    public function run(): void
    {
        $fans = [
            ['name' => 'Fiqih', 'arabic_name' => 'فقه'],
            ['name' => 'Ushul Fiqih', 'arabic_name' => 'أصول الفقه'],
            ['name' => 'Tauhid / Aqidah', 'arabic_name' => 'توحيد'],
            ['name' => 'Tasawuf / Akhlak', 'arabic_name' => 'تصوف'],
            ['name' => 'Nahwu', 'arabic_name' => 'نحو'],
            ['name' => 'Sharaf', 'arabic_name' => 'صرف'],
            ['name' => 'Balaghah', 'arabic_name' => 'بلاغة'],
            ['name' => 'Tafsir', 'arabic_name' => 'تفسير'],
            ['name' => "Ulumul Qur'an", 'arabic_name' => 'علوم القرآن'],
            ['name' => 'Hadits', 'arabic_name' => 'حديث'],
            ['name' => 'Musthalah Hadits', 'arabic_name' => 'مصطلح الحديث'],
            ['name' => 'Sirah / Tarikh', 'arabic_name' => 'سيرة / تاريخ'],
            ['name' => 'Mantiq', 'arabic_name' => 'منطق'],
            ['name' => 'Faraidh', 'arabic_name' => 'فرائض'],
            ['name' => "Qira'at", 'arabic_name' => 'قراءات'],
        ];

        foreach ($fans as $index => $fan) {
            Fan::query()->updateOrCreate(
                ['name' => $fan['name']],
                [
                    'description' => $fan['arabic_name'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
