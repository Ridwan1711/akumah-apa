<?php

namespace Database\Seeders;

use App\Models\Diniyyah\Fan;
use App\Models\Diniyyah\Subject;
use Illuminate\Database\Seeder;

class SubjectFanBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $fanMap = Fan::query()->pluck('id', 'name');
        if ($fanMap->isEmpty()) {
            return;
        }

        $uncategorized = Fan::query()->firstOrCreate(
            ['name' => 'Uncategorized'],
            ['description' => 'Belum dipetakan ke fan tertentu.', 'sort_order' => 999, 'is_active' => true]
        );

        $keywordRules = [
            'Fiqih' => ['fiqih', 'fiqh', 'safinah', 'fathul qarib', 'fath al qarib', 'fathul qorib', 'sullam taufiq', 'sulam'],
            'Ushul Fiqih' => ['ushul fiqih', 'ushul fiqh', 'ushul fiqh', 'qawaid fiqh', 'qawaid ushul'],
            'Tauhid / Aqidah' => ['tauhid', 'aqidah', 'aqidatul', 'aqidatul awam', 'aqidatul awwam', 'jauharat tauhid', 'tijan'],
            'Tasawuf / Akhlak' => ['tasawuf', 'akhlak', "ta'lim", 'talim', 'ta lim', 'bidayatul hidayah', 'adab'],
            'Nahwu' => ['nahwu', 'jurumiyah', 'imrithi', 'alfiyah'],
            'Sharaf' => ['sharaf', 'sorof', 'shorof', 'amtsilah', 'tasrif'],
            'Balaghah' => ['balaghah', 'jawahirul balaghah', 'maani', 'bayan', "badi'"],
            'Tafsir' => ['tafsir', 'jalalain', 'ibnu katsir', 'ibn katsir'],
            "Ulumul Qur'an" => ['ulumul quran', "ulumul qur'an", 'ulum quran'],
            'Hadits' => ['hadits', 'hadith', "arba'in", 'riyadhus shalihin', 'bulughul maram'],
            'Musthalah Hadits' => ['musthalah', 'musthalah hadits', 'mustholah'],
            'Sirah / Tarikh' => ['sirah', 'siroh', 'tarikh', 'sejarah islam'],
            'Mantiq' => ['mantiq', 'logika'],
            'Faraidh' => ['faraidh', 'faroid', 'waris'],
            "Qira'at" => ["qira'at", 'qiraat', 'tajwid', 'tilawah'],
        ];

        Subject::query()->orderBy('id')->chunkById(200, function ($subjects) use ($fanMap, $keywordRules, $uncategorized) {
            /** @var Subject $subject */
            foreach ($subjects as $subject) {
                if ($subject->fan_id) {
                    continue;
                }

                $name = mb_strtolower((string) $subject->name);
                $selectedFanId = null;

                foreach ($keywordRules as $fanName => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (str_contains($name, mb_strtolower($keyword))) {
                            $selectedFanId = (int) ($fanMap[$fanName] ?? 0);
                            break 2;
                        }
                    }
                }

                $subject->fan_id = $selectedFanId > 0 ? $selectedFanId : $uncategorized->id;
                $subject->save();
            }
        });
    }
}
