<?php

namespace App\Services\Imports;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\Student;
use App\Services\Diniyyah\StudentEnrollmentService;
use Illuminate\Support\Facades\Validator;

class EnrollmentImportRowProcessor
{
    public function __construct(
        protected StudentEnrollmentService $enrollmentService
    ) {}

    public function process(array $data, string $strategy): array
    {
        $validator = Validator::make($data, [
            'nis' => ['required', 'string', 'max:20'],
            'class_id' => ['nullable', 'integer'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer'],
            'period_name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first()];
        }

        $student = Student::query()->where('nis', (string) $data['nis'])->first();
        if (! $student) {
            return ['status' => 'failed', 'message' => 'NIS tidak ditemukan.'];
        }

        $period = $this->resolvePeriod($data);
        if (! $period) {
            return ['status' => 'failed', 'message' => 'Periode akademik tidak ditemukan (gunakan period_id/period_name).'];
        }

        $class = $this->resolveClass($data);
        if (! $class) {
            return ['status' => 'failed', 'message' => 'Kelas tidak ditemukan (gunakan class_id/class_name).'];
        }

        $existing = StudentClassEnrollment::query()
            ->where('student_id', $student->id)
            ->where('period_id', $period->id)
            ->first();

        if ($existing && $strategy === 'skip') {
            return ['status' => 'skipped', 'message' => null];
        }

        $summary = $this->enrollmentService->execute(
            [$student->id],
            $class->id,
            $period->id,
            StudentEnrollmentService::MODE_ASSIGN,
            true,
        );

        if (($summary['failed'] ?? 0) > 0) {
            $message = $summary['results'][0]['message'] ?? 'Gagal memproses enrollment.';
            return ['status' => 'failed', 'message' => $message];
        }

        if (($summary['created'] ?? 0) > 0) {
            return ['status' => 'created', 'message' => null];
        }

        if (($summary['updated'] ?? 0) > 0) {
            return ['status' => 'updated', 'message' => null];
        }

        return ['status' => 'skipped', 'message' => null];
    }

    protected function resolvePeriod(array $data): ?AcademicPeriod
    {
        if (! empty($data['period_id'])) {
            return AcademicPeriod::query()->find((int) $data['period_id']);
        }

        if (! empty($data['period_name'])) {
            return AcademicPeriod::query()->where('name', (string) $data['period_name'])->first();
        }

        return null;
    }

    protected function resolveClass(array $data): ?SchoolClass
    {
        if (! empty($data['class_id'])) {
            return SchoolClass::query()->find((int) $data['class_id']);
        }

        if (! empty($data['class_name'])) {
            return SchoolClass::query()->where('name', (string) $data['class_name'])->first();
        }

        return null;
    }
}

