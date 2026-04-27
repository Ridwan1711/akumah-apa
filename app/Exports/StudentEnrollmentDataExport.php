<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentEnrollmentDataExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected int $periodId,
        protected array $filters = []
    ) {}

    public function query(): Builder
    {
        return Student::query()
            ->with([
                'currentClass:id,name',
                'classEnrollments' => fn ($query) => $query
                    ->where('period_id', $this->periodId)
                    ->with('schoolClass:id,name')
                    ->select(['id', 'student_id', 'class_id', 'period_id']),
            ])
            ->when($this->filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('nis', 'ilike', "%{$search}%");
                });
            })
            ->when($this->filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($this->filters['class_id'] ?? null, fn (Builder $query, string $classId) => $query->where('current_class_id', $classId))
            ->orderBy('full_name');
    }

    public function headings(): array
    {
        return [
            'nis',
            'full_name',
            'status',
            'kelas_saat_ini',
            'kelas_enrollment',
        ];
    }

    public function map($student): array
    {
        $enrollment = $student->classEnrollments->first();

        return [
            $student->nis,
            $student->full_name,
            $student->status,
            $student->currentClass?->name,
            $enrollment?->schoolClass?->name,
        ];
    }
}
