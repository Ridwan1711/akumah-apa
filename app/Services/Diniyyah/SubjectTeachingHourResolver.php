<?php

namespace App\Services\Diniyyah;

use App\Models\Diniyyah\SubjectClassOverride;
use App\Models\Diniyyah\SubjectLevelSetting;
use App\Models\Diniyyah\TeacherAssignment;

class SubjectTeachingHourResolver
{
    public function resolve(int $classId, int $subjectId, int $periodId, ?int $requestedTargetJam = null): int
    {
        if ($requestedTargetJam !== null && $requestedTargetJam > 0) {
            return $requestedTargetJam;
        }

        $overrideHours = SubjectClassOverride::query()
            ->where('class_id', $classId)
            ->whereHas('levelSetting', function ($query) use ($subjectId, $periodId): void {
                $query->where('subject_id', $subjectId)
                    ->where('period_id', $periodId);
            })
            ->value('override_hours');

        if ($overrideHours !== null && (int) $overrideHours > 0) {
            return (int) $overrideHours;
        }

        $defaultHours = SubjectLevelSetting::query()
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->whereHas('level.classes', fn ($query) => $query->where('id', $classId))
            ->value('target_jam_default');

        if ($defaultHours !== null && (int) $defaultHours > 0) {
            return (int) $defaultHours;
        }

        return 1;
    }

    public function resolveForAssignment(TeacherAssignment $assignment): int
    {
        return $this->resolve(
            (int) $assignment->class_id,
            (int) $assignment->subject_id,
            (int) $assignment->period_id,
            (int) ($assignment->target_jam ?? 0)
        );
    }
}
