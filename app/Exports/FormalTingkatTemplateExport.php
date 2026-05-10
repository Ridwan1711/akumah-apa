<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FormalTingkatTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new FormalTingkatReferensiSheet,
            new FormalTingkatTemplateEnrollmentSheet,
        ];
    }
}
