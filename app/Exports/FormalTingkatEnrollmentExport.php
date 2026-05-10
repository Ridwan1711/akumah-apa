<?php

namespace App\Exports;

use App\Models\EnrollmentTingkatSekolah;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FormalTingkatEnrollmentExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly int $academicYearId,
    ) {}

    public function headings(): array
    {
        return [
            'academic_year_name',
            'nis',
            'student_name',
            'tingkat_sekolah_id',
            'tingkat_code',
            'tingkat_name',
        ];
    }

    public function collection()
    {
        return EnrollmentTingkatSekolah::query()
            ->where('academic_year_id', $this->academicYearId)
            ->with([
                'student:id,nis,full_name',
                'tingkatSekolah:id,name,code',
                'academicYear:id,name',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (EnrollmentTingkatSekolah $e): array {
                return [
                    'academic_year_name' => (string) ($e->academicYear?->name ?? ''),
                    'nis' => (string) ($e->student?->nis ?? ''),
                    'student_name' => (string) ($e->student?->full_name ?? ''),
                    'tingkat_sekolah_id' => (int) $e->tingkat_sekolah_id,
                    'tingkat_code' => (string) ($e->tingkatSekolah?->code ?? ''),
                    'tingkat_name' => (string) ($e->tingkatSekolah?->name ?? ''),
                ];
            });
    }
}
