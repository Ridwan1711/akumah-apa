<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Sheet referensi di template (bukan sumber kebenaran impor). Baris diabaikan.
 */
class FormalTingkatReferensiSheetImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        // Intentionally no-op: Referensi_Tingkat hanya panduan operator.
    }
}
