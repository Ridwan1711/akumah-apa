<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DormTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new DormTemplateGedungKamarSheet,
            new DormTemplatePenempatanSheet,
        ];
    }
}
