<?php

namespace App\Exports;

use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
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
        $firstGl = GradeLevel::query()->orderBy('order')->orderBy('id')->first();

        if (! $firstGl) {
            return [
                [
                    'Buat jenjang di menu master terlebih dahulu',
                    '',
                    '',
                    '',
                    '',
                ],
            ];
        }

        $maxOrder = (int) SchoolClass::query()->max('order');
        $o1 = max(1, $maxOrder + 1);
        $o2 = $o1 + 1;

        return [
            [
                'Contoh: '.$firstGl->name.' A',
                (string) $firstGl->id,
                '',
                (string) $o1,
                'L',
            ],
            [
                'Contoh: '.$firstGl->name.' B',
                '',
                $firstGl->name,
                (string) $o2,
                'P',
            ],
        ];
    }
}
