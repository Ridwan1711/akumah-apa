<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TeacherTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'user_id',
            'student_nis',
            'name',
            'username',
            'email',
            'is_active',
        ];
    }

    public function array(): array
    {
        return [
            // Mode 1 (recommended): assign existing user as teacher (by student NIS)
            ['', 'MH24001AB', '', '', '', '1'],

            // Mode 2: assign existing user as teacher (by user_id)
            ['123', '', '', '', '', '1'],

            // Mode 3 (legacy): create/update teacher by email
            ['', '', 'Ustadz Abdullah', 'abdullah.guru', 'abdullah.guru@example.com', '1'],
        ];
    }
}
