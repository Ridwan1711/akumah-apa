<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\RoleCertificate;
use App\Models\StudentPosition;
use Carbon\CarbonInterface;

class RoleCertificateService
{
    public function issueForTeacherAssignment(TeacherAssignment $assignment, ?int $createdBy = null): RoleCertificate
    {
        $periodId = (int) $assignment->period_id;
        $sourceKey = sprintf('teacher:%d:period:%d', $assignment->teacher_id, $periodId);
        $existing = RoleCertificate::query()
            ->where('certificate_type', RoleCertificate::TYPE_TEACHER)
            ->where('source_key', $sourceKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $payload = [
            'teacher_name' => $assignment->teacher?->name,
            'class_subject_assignments' => TeacherAssignment::query()
                ->with(['schoolClass:id,name', 'subject:id,name'])
                ->where('teacher_id', $assignment->teacher_id)
                ->where('period_id', $periodId)
                ->get()
                ->map(fn (TeacherAssignment $item) => [
                    'class_id' => $item->class_id,
                    'class_name' => $item->schoolClass?->name,
                    'subject_id' => $item->subject_id,
                    'subject_name' => $item->subject?->name,
                    'target_jam' => $item->target_jam,
                ])
                ->values()
                ->all(),
        ];

        return $this->createCertificate([
            'certificate_type' => RoleCertificate::TYPE_TEACHER,
            'issuance_mode' => RoleCertificate::MODE_AUTO,
            'source_key' => $sourceKey,
            'user_id' => $assignment->teacher_id,
            'student_position_id' => null,
            'academic_period_id' => $periodId,
            'payload' => $payload,
            'created_by' => $createdBy,
            'status' => RoleCertificate::STATUS_ISSUED,
        ]);
    }

    public function issueForStudentPosition(StudentPosition $studentPosition, ?int $createdBy = null): ?RoleCertificate
    {
        if (! $studentPosition->is_active) {
            return null;
        }

        $periodId = (int) (AcademicPeriod::query()->active()->value('id') ?? 0);
        $sourceKey = sprintf('student_position:%d:period:%d', $studentPosition->id, $periodId);
        $existing = RoleCertificate::query()
            ->where('certificate_type', RoleCertificate::TYPE_STUDENT_POSITION)
            ->where('source_key', $sourceKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $payload = [
            'student_name' => $studentPosition->student?->full_name,
            'position_type' => $studentPosition->position_type,
            'division_code' => $studentPosition->division_code,
        ];

        return $this->createCertificate([
            'certificate_type' => RoleCertificate::TYPE_STUDENT_POSITION,
            'issuance_mode' => RoleCertificate::MODE_AUTO,
            'source_key' => $sourceKey,
            'user_id' => null,
            'student_position_id' => $studentPosition->id,
            'academic_period_id' => $periodId > 0 ? $periodId : null,
            'payload' => $payload,
            'valid_from' => $studentPosition->started_at?->toDateString(),
            'valid_until' => $studentPosition->ended_at?->toDateString(),
            'created_by' => $createdBy,
            'status' => RoleCertificate::STATUS_ISSUED,
        ]);
    }

    public function issueManual(array $attributes): RoleCertificate
    {
        $sourceKey = (string) ($attributes['source_key'] ?? '');
        if ($sourceKey === '') {
            $sourceKey = sprintf(
                '%s:%s:%s',
                $attributes['certificate_type'],
                $attributes['user_id'] ?? $attributes['student_position_id'] ?? 'na',
                $attributes['academic_period_id'] ?? 'na'
            );
        }

        RoleCertificate::query()
            ->where('certificate_type', $attributes['certificate_type'])
            ->where('source_key', $sourceKey)
            ->update(['status' => RoleCertificate::STATUS_REISSUED]);

        return $this->createCertificate([
            ...$attributes,
            'issuance_mode' => RoleCertificate::MODE_MANUAL,
            'source_key' => $sourceKey,
            'status' => RoleCertificate::STATUS_ISSUED,
            'reissued_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCertificate(array $attributes): RoleCertificate
    {
        $now = now();

        return RoleCertificate::query()->create([
            'certificate_number' => $this->nextCertificateNumber($now),
            'certificate_type' => $attributes['certificate_type'],
            'issuance_mode' => $attributes['issuance_mode'] ?? RoleCertificate::MODE_AUTO,
            'status' => $attributes['status'] ?? RoleCertificate::STATUS_ISSUED,
            'source_key' => $attributes['source_key'],
            'user_id' => $attributes['user_id'] ?? null,
            'student_position_id' => $attributes['student_position_id'] ?? null,
            'academic_period_id' => $attributes['academic_period_id'] ?? null,
            'valid_from' => $attributes['valid_from'] ?? null,
            'valid_until' => $attributes['valid_until'] ?? null,
            'payload' => $attributes['payload'] ?? [],
            'issued_at' => $attributes['issued_at'] ?? $now,
            'reissued_at' => $attributes['reissued_at'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $attributes['created_by'] ?? null,
        ]);
    }

    private function nextCertificateNumber(CarbonInterface $at): string
    {
        $prefix = 'SK-'.$at->format('Ymd');
        $count = RoleCertificate::query()
            ->whereDate('created_at', $at->toDateString())
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }
}
