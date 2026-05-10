<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class InvoiceTemplateDataSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Tagihan';
    }

    public function headings(): array
    {
        return [
            'nis',
            'payment_type_code',
            'academic_year_name',
            'academic_year_id',
            'month',
            'amount',
            'discount_amount',
            'due_date',
            'notes',
            'breakdown_json',
        ];
    }

    public function array(): array
    {
        return [
            ['MH2025001', 'SPP', '2025/2026', '', '7', '1500000', '0', '2025-08-10', '', ''],
        ];
    }
}
