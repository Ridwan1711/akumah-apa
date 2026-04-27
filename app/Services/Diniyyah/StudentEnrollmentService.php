<?php

namespace App\Services\Diniyyah;

use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentEnrollmentService
{
    public const MODE_ASSIGN = 'assign';
    public const MODE_MOVE = 'move';
    public const MODE_CLEAR = 'clear';

    /**
     * @param  array<int>  $studentIds
     * @return array<string, mixed>
     */
    public function preview(array $studentIds, ?int $classId, int $periodId, string $mode): array
    {
        return $this->process($studentIds, $classId, $periodId, $mode, true);
    }

    /**
     * @param  array<int>  $studentIds
     * @return array<string, mixed>
     */
    public function execute(array $studentIds, ?int $classId, int $periodId, string $mode, bool $rejectInactive = true): array
    {
        return $this->process($studentIds, $classId, $periodId, $mode, false, $rejectInactive);
    }

    /**
     * @param  array<int>  $studentIds
     * @return array<string, mixed>
     */
    protected function process(
        array $studentIds,
        ?int $classId,
        int $periodId,
        string $mode,
        bool $dryRun = false,
        bool $rejectInactive = true,
    ): array {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->get(['id', 'full_name', 'status', 'current_class_id', 'gender'])
            ->keyBy('id');
        $targetClass = ($classId !== null && $classId > 0)
            ? SchoolClass::query()->find($classId)
            : null;

        $rows = StudentClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('period_id', $periodId)
            ->get(['id', 'student_id', 'class_id', 'period_id'])
            ->keyBy('student_id');

        $results = [];
        $summary = [
            'mode' => $mode,
            'period_id' => $periodId,
            'target_class_id' => $classId,
            'total' => count($studentIds),
            'created' => 0,
            'updated' => 0,
            'cleared' => 0,
            'skipped' => 0,
            'failed' => 0,
            'results' => &$results,
        ];

        foreach ($studentIds as $studentId) {
            /** @var \App\Models\Student|null $student */
            $student = $students->get($studentId);
            if (! $student) {
                $summary['failed']++;
                $results[] = $this->resultRow($studentId, null, 'failed', 'Santri tidak ditemukan.');
                continue;
            }

            if ($rejectInactive && $student->status !== Student::STATUS_ACTIVE) {
                $summary['failed']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'failed', 'Santri tidak aktif.');
                continue;
            }

            /** @var \App\Models\Diniyyah\StudentClassEnrollment|null $existing */
            $existing = $rows->get($studentId);
            $fromClassId = $existing?->class_id;

            if ($mode === self::MODE_CLEAR) {
                if (! $existing) {
                    $summary['skipped']++;
                    $results[] = $this->resultRow($studentId, $student->full_name, 'skipped', 'Tidak ada enrollment untuk periode ini.');
                    continue;
                }

                if (! $dryRun) {
                    DB::transaction(function () use ($existing, $student, $fromClassId) {
                        $existing->delete();
                        if ((int) $student->current_class_id === (int) $fromClassId) {
                            $student->update(['current_class_id' => null]);
                        }
                    });
                }

                $summary['cleared']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'cleared', 'Enrollment dihapus.');
                continue;
            }

            if ($classId === null || $classId <= 0) {
                $summary['failed']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'failed', 'Class tujuan wajib dipilih.');
                continue;
            }
            if (! $targetClass) {
                $summary['failed']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'failed', 'Class tujuan tidak ditemukan.');
                continue;
            }
            if (! $targetClass->acceptsStudentGender($student->gender)) {
                $summary['failed']++;
                $studentGender = $student->gender === Student::GENDER_MALE ? 'Santriyyin' : ($student->gender === Student::GENDER_FEMALE ? 'Santriyah' : 'Tidak diset');
                $results[] = $this->resultRow(
                    $studentId,
                    $student->full_name,
                    'failed',
                    "Kelas {$targetClass->name} hanya untuk {$targetClass->student_gender_label}. Gender santri saat ini: {$studentGender}."
                );
                continue;
            }

            if ($existing && (int) $existing->class_id === (int) $classId) {
                $summary['skipped']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'skipped', 'Sudah terdaftar di kelas tujuan.');
                continue;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($studentId, $periodId, $classId, $student) {
                    StudentClassEnrollment::query()->updateOrCreate(
                        [
                            'student_id' => $studentId,
                            'period_id' => $periodId,
                        ],
                        [
                            'class_id' => $classId,
                        ]
                    );

                    $student->update(['current_class_id' => $classId]);
                });
            }

            if ($existing) {
                $summary['updated']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'updated', 'Enrollment dipindahkan ke kelas baru.');
            } else {
                $summary['created']++;
                $results[] = $this->resultRow($studentId, $student->full_name, 'created', 'Enrollment berhasil dibuat.');
            }
        }

        return $summary;
    }

    protected function resultRow(int $studentId, ?string $studentName, string $status, string $message): array
    {
        return [
            'student_id' => $studentId,
            'student_name' => $studentName,
            'status' => $status,
            'message' => $message,
        ];
    }
}

