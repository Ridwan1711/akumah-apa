<?php

namespace App\Exports;

use App\Models\TingkatSekolah;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class FormalTingkatTemplateEnrollmentSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Enrollment';
    }

    public function headings(): array
    {
        return [
            'academic_year_name',
            'nis',
            'tingkat_code',
        ];
    }

    public function array(): array
    {
        return [
            ['2025/2026', 'MH2025001', TingkatSekolah::CODE_MTS_7],
        ];
    }
}
