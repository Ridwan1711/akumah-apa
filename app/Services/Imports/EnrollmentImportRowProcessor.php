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

    public function process(array $data, string $strategy, array $context = []): array
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

        $period = $this->resolvePeriod($data, $context);
        if (! $period) {
            return ['status' => 'failed', 'message' => 'Periode akademik tidak ditemukan (isi period_id, period_name yang cocok dengan tahun ajaran + semester, atau pilih periode default di halaman sebelum unggah).'];
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

    protected function resolvePeriod(array $data, array $context = []): ?AcademicPeriod
    {
        $periodId = isset($data['period_id']) && $data['period_id'] !== '' && $data['period_id'] !== null
            ? (int) $data['period_id']
            : 0;
        if ($periodId > 0) {
            return AcademicPeriod::query()->find($periodId);
        }

        if (! empty($data['period_name'])) {
            $needle = $this->normalizePeriodLabel((string) $data['period_name']);
            if ($needle !== '') {
                $periods = AcademicPeriod::query()
                    ->with(['academicYear', 'semester'])
                    ->orderBy('id')
                    ->get();

                foreach ($periods as $period) {
                    /** @var AcademicPeriod $period */
                    $label = $this->normalizePeriodLabel($this->buildPeriodDisplayLabel($period));
                    if ($label !== '' && $label === $needle) {
                        return $period;
                    }
                }
            }
        }

        $defaultId = (int) ($context['default_period_id'] ?? 0);
        if ($defaultId > 0) {
            return AcademicPeriod::query()->find($defaultId);
        }

        return null;
    }

    protected function buildPeriodDisplayLabel(AcademicPeriod $period): string
    {
        $year = $period->relationLoaded('academicYear')
            ? ($period->academicYear?->name ?? '')
            : ($period->academicYear()->value('name') ?? '');
        $semester = $period->relationLoaded('semester')
            ? ($period->semester?->name ?? '')
            : ($period->semester()->value('name') ?? '');

        return trim(trim((string) $year).' '.trim((string) $semester));
    }

    protected function normalizePeriodLabel(string $value): string
    {
        $lower = mb_strtolower($value, 'UTF-8');
        $lower = str_replace(['—', '–', '-', '/', '\\', ','], ' ', $lower);
        $lower = preg_replace('/\s+/u', ' ', $lower) ?? '';

        return trim($lower);
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

