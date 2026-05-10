<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InvoiceTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new InvoiceTemplateDataSheet,
            new InvoiceTemplatePetunjukSheet,
        ];
    }
}
