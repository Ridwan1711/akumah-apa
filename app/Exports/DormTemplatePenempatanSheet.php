<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DormTemplatePenempatanSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Penempatan';
    }

    public function headings(): array
    {
        return [
            'academic_year_name',
            'nis',
            'building_name',
            'room_number',
            'checkin_date',
            'checkout_date',
        ];
    }

    public function array(): array
    {
        return [
            ['2025/2026', 'MH2025001', 'Kobong A', '101', '2025-07-01', ''],
        ];
    }
}
