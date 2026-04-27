<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KitabSubjectTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
        ];
    }

    public function array(): array
    {
        return [
            ['Nahwu'],
            ['Sharaf'],
            ['Fiqih'],
        ];
    }
}
