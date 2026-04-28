<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DiniyahClassTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
            'grade_level_id',
            'grade_level_name',
            'order',
            'student_gender',
        ];
    }

    public function array(): array
    {
        return [
            ['Ula 1A', '1', '', '1', 'L'],
            ['Ula 1B', '', 'Ula', '2', 'P'],
        ];
    }
}
