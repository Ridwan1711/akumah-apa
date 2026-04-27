<?php

namespace App\Services\Diniyyah;

use App\Models\Diniyyah\ClassWali;
use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\User;
use InvalidArgumentException;

class ScoreWorkflow
{
    public function assertTransition(string $from, string $to): void
    {
        $allowed = match ($from) {
            Score::STATUS_DRAFT => [Score::STATUS_SUBMITTED],
            Score::STATUS_SUBMITTED => [Score::STATUS_FINALIZED, Score::STATUS_DRAFT],
            Score::STATUS_FINALIZED => [],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Invalid score status transition: {$from} → {$to}");
        }
    }

    /**
     * Only homeroom teacher (wali) for the student's class in this period may finalize.
     */
    public function assertCanFinalize(User $user, Score $score): void
    {
        $enrollment = StudentClassEnrollment::query()
            ->where('student_id', $score->student_id)
            ->where('period_id', $score->period_id)
            ->first();

        $classId = $enrollment?->class_id ?? $score->student?->current_class_id;

        if ($classId === null) {
            abort(403, 'Tidak dapat menentukan kelas santri untuk periode ini.');
        }

        $wali = ClassWali::query()
            ->where('class_id', $classId)
            ->where('period_id', $score->period_id)
            ->where('teacher_id', $user->id)
            ->exists();

        if (! $wali && ! $user->isAdmin()) {
            abort(403, 'Hanya wali kelas yang dapat memfinalisasi nilai untuk periode ini.');
        }
    }
}
