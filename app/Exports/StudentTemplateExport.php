<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'nis',
            'full_name',
            'admission_year',
            'gender',
        ];
    }

    public function array(): array
    {
        return [
            ['230001', 'Ahmad Fulan', '2023', 'L'],
            ['230002', 'Aisyah Fulanah', '2023', 'P'],
        ];
    }
}
