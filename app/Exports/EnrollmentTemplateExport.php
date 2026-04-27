<?php

namespace App\Exports;

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
        return [
            ['230001', '1', '1', '', ''],
            ['230002', '', '', 'Ibtida A', '2026 Semester 1'],
        ];
    }
}

