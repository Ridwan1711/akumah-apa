<?php

namespace App\Exports;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EnrollmentTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nis',
            'class_id',
            'period_id',
            'class_name',
            'period_name',
        ];
    }

    public function array(): array
    {
        $student = Student::query()->whereNotNull('nis')->orderBy('id')->first();
        $class = SchoolClass::query()->orderBy('id')->first();
        $period = AcademicPeriod::query()
            ->with(['academicYear', 'semester'])
            ->orderBy('id')
            ->first();

        $nis = $student?->nis ?? '230001';
        $classId = $class !== null ? (string) $class->id : '1';
        $periodId = $period !== null ? (string) $period->id : '1';
        $className = $class?->name ?? 'Ibtida A';

        $periodName = '';
        if ($period !== null) {
            $year = $period->academicYear?->name ?? '';
            $sem = $period->semester?->name ?? '';
            $periodName = trim(trim((string) $year).' '.trim((string) $sem));
        }

        if ($period === null || $class === null) {
            return [
                [$nis, $classId, $periodId, '', ''],
                ['230002', '', '', 'Ibtida A', ''],
            ];
        }

        return [
            [$nis, $classId, $periodId, '', ''],
            [$nis, '', '', $className, $periodName],
        ];
    }
}
