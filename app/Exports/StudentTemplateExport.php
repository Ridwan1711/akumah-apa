<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'full_name',
            'admission_year',
            'gender',
            'nik',
            'nis',
        ];
    }

    public function array(): array
    {
        return [
            ['Ahmad Fulan', '2023', 'L', '', ''],
            ['Aisyah Fulanah', '2023', 'P', '', ''],
        ];
    }
}
