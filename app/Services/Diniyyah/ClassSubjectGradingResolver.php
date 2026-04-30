<?php

namespace App\Services\Diniyyah;

use App\Models\Diniyyah\ClassSubject;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\SubjectLevelSetting;

class ClassSubjectGradingResolver
{
    /**
     * Resolve class_subjects row: period-specific first, then global (period_id null).
     */
    public function resolve(int $classId, int $subjectId, int $periodId): ?ClassSubject
    {
        $specific = ClassSubject::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->first();

        if ($specific) {
            return $specific;
        }

        return ClassSubject::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereNull('period_id')
            ->first();
    }

    public function gradingEnabled(int $classId, int $subjectId, int $periodId): bool
    {
        $row = $this->resolve($classId, $subjectId, $periodId);
        if ($row !== null) {
            return $row->is_active && $row->has_score;
        }

        $levelId = (int) (SchoolClass::query()->whereKey($classId)->value('grade_level_id') ?? 0);
        if ($levelId <= 0) {
            return false;
        }

        $default = SubjectLevelSetting::query()
            ->where('level_id', $levelId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->first();

        if ($default) {
            return $default->is_mandatory_teaching && $default->has_score_default;
        }

        return false;
    }
}
