<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TeacherTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
            'username',
            'email',
            'is_active',
        ];
    }

    public function array(): array
    {
        return [
            ['Ustadz Abdullah', 'abdullah.guru', 'abdullah.guru@example.com', '1'],
        ];
    }
}
