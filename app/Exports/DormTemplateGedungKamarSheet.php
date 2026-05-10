<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DormTemplateGedungKamarSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Gedung_Kamar';
    }

    public function headings(): array
    {
        return [
            'building_name',
            'building_description',
            'room_number',
            'capacity',
            'floor',
        ];
    }

    public function array(): array
    {
        return [
            ['Kobong A', 'Gedung utama asrama putra', '101', '4', '1'],
            ['Kobong A', '', '102', '4', '1'],
        ];
    }
}
