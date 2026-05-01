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
        $payload = $this->teacherPayload((int) $assignment->teacher_id, $periodId);

        if ($existing) {
            $existing->update([
                'payload' => $payload,
                ...$this->missingSignatureAttributes($existing),
            ]);

            return $existing;
        }

        return $this->createCertificate([
            'certificate_type' => RoleCertificate::TYPE_TEACHER,
            'issuance_mode' => RoleCertificate::MODE_AUTO,
            'source_key' => $sourceKey,
            'user_id' => $assignment->teacher_id,
            'student_position_id' => null,
            'academic_period_id' => $periodId,
            ...$this->periodValidityAttributes($periodId),
            ...$this->defaultSignatureAttributes(),
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
            ...$this->defaultSignatureAttributes(),
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

        if (
            ($attributes['certificate_type'] ?? null) === RoleCertificate::TYPE_TEACHER
            && ! empty($attributes['user_id'])
            && ! empty($attributes['academic_period_id'])
        ) {
            $attributes['payload'] = $this->teacherPayload(
                (int) $attributes['user_id'],
                (int) $attributes['academic_period_id']
            );
        }

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
            'principal_name' => $attributes['principal_name'] ?? null,
            'principal_title' => $attributes['principal_title'] ?? null,
            'stamp_path' => $attributes['stamp_path'] ?? null,
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

    /**
     * @return array{valid_from: string|null, valid_until: string|null}
     */
    private function periodValidityAttributes(int $periodId): array
    {
        $period = AcademicPeriod::query()
            ->with('semester:id,start_date,end_date')
            ->find($periodId);

        return [
            'valid_from' => $period?->semester?->start_date?->toDateString(),
            'valid_until' => $period?->semester?->end_date?->toDateString(),
        ];
    }

    /**
     * @return array{principal_name: string|null, principal_title: string|null, stamp_path: string|null}
     */
    private function defaultSignatureAttributes(): array
    {
        $configured = [
            'principal_name' => $this->nullableConfigString('role_certificate.defaults.principal_name'),
            'principal_title' => $this->nullableConfigString('role_certificate.defaults.principal_title') ?? 'Kepala Sekolah / Pimpinan',
            'stamp_path' => $this->nullableConfigString('role_certificate.defaults.stamp_path'),
        ];

        if ($configured['principal_name'] !== null || $configured['stamp_path'] !== null) {
            return $configured;
        }

        $latestSnapshot = RoleCertificate::query()
            ->whereNotNull('principal_name')
            ->latest('id')
            ->first(['principal_name', 'principal_title', 'stamp_path']);

        return [
            'principal_name' => $latestSnapshot?->principal_name,
            'principal_title' => $latestSnapshot?->principal_title ?? $configured['principal_title'],
            'stamp_path' => $latestSnapshot?->stamp_path,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function missingSignatureAttributes(RoleCertificate $certificate): array
    {
        $defaults = $this->defaultSignatureAttributes();

        return collect($defaults)
            ->filter(fn (?string $value, string $key): bool => $value !== null && blank($certificate->{$key}))
            ->all();
    }

    private function nullableConfigString(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @return array{teacher_name: string|null, class_subject_assignments: list<array{subject_id: int, subject_name: string|null, class_names: string, total_jam: int}>}
     */
    private function teacherPayload(int $teacherId, int $periodId): array
    {
        $assignments = TeacherAssignment::query()
            ->with(['teacher:id,name', 'schoolClass:id,name', 'subject:id,name'])
            ->where('teacher_id', $teacherId)
            ->where('period_id', $periodId)
            ->orderBy('subject_id')
            ->orderBy('class_id')
            ->get();

        return [
            'teacher_name' => $assignments->first()?->teacher?->name,
            'class_subject_assignments' => $assignments
                ->groupBy('subject_id')
                ->map(fn ($items) => [
                    'subject_id' => (int) $items->first()->subject_id,
                    'subject_name' => $items->first()->subject?->name,
                    'class_names' => $items
                        ->pluck('schoolClass.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', '),
                    'total_jam' => (int) $items->sum(fn (TeacherAssignment $item) => (int) ($item->target_jam ?? 0)),
                ])
                ->values()
                ->all(),
        ];
    }
}
