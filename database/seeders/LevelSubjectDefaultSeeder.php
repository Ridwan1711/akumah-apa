<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use App\Models\Diniyyah\SubjectLevelSetting;
use Illuminate\Database\Seeder;

class LevelSubjectDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $periodId = (int) (AcademicPeriod::query()->where('is_active', true)->value('id') ?? 0);
        if ($periodId <= 0) {
            $periodId = (int) (AcademicPeriod::query()->value('id') ?? 0);
        }
        if ($periodId <= 0) {
            return;
        }

        $subjects = Subject::query()->get(['id', 'name']);
        if ($subjects->isEmpty()) {
            return;
        }

        // Baseline default: semua kitab dinilai + wajib diajarkan 1 jam di tiap level.
        foreach (SchoolClass::LEVELS as $levelTag) {
            foreach ($subjects as $subject) {
                SubjectLevelSetting::query()->updateOrCreate(
                    [
                        'level_tag' => $levelTag,
                        'subject_id' => (int) $subject->id,
                        'period_id' => $periodId,
                    ],
                    [
                        'has_score_default' => true,
                        'target_jam_default' => 1,
                        'is_mandatory_teaching' => true,
                    ]
                );
            }
        }

        // Override contoh domain pesantren (1salafy).
        $special = [
            ['keyword' => 'safinah', 'has_score_default' => true, 'target_jam_default' => 3],
            ['keyword' => 'sulam', 'has_score_default' => false, 'target_jam_default' => 2],
        ];

        foreach ($special as $cfg) {
            $subject = Subject::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($cfg['keyword']).'%'])
                ->first();

            if (! $subject) {
                continue;
            }

            SubjectLevelSetting::query()->updateOrCreate(
                [
                    'level_tag' => SchoolClass::LEVEL_SALAFY1,
                    'subject_id' => (int) $subject->id,
                    'period_id' => $periodId,
                ],
                [
                    'has_score_default' => $cfg['has_score_default'],
                    'target_jam_default' => $cfg['target_jam_default'],
                    'is_mandatory_teaching' => true,
                ]
            );
        }
    }
}
