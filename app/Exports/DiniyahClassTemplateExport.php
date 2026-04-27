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
            'level_order',
            'level',
            'student_gender',
        ];
    }

    public function array(): array
    {
        return [
            ['Ula 1A', '1', '', '1', 'ibtida', 'L'],
            ['Ula 1B', '', 'Ula', '2', 'ibtida', 'P'],
        ];
    }
}
