<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FormalTingkatDataImport implements WithMultipleSheets
{
    private readonly FormalTingkatImportResult $aggregate;

    public function __construct(
        private readonly string $enrollmentStrategy,
    ) {
        $this->aggregate = new FormalTingkatImportResult;
    }

    public function sheets(): array
    {
        return [
            'Referensi_Tingkat' => new FormalTingkatReferensiSheetImport,
            'Enrollment' => new FormalTingkatEnrollmentSheetImport($this->aggregate, $this->enrollmentStrategy),
        ];
    }

    /**
     * @return array{processed:int, created:int, updated:int, skipped:int, failed:int, errors: list<array{row:int|string, message:string}>}
     */
    public function result(): array
    {
        return [
            'processed' => $this->aggregate->processed,
            'created' => $this->aggregate->created,
            'updated' => $this->aggregate->updated,
            'skipped' => $this->aggregate->skipped,
            'failed' => $this->aggregate->failed,
            'errors' => $this->aggregate->errors,
        ];
    }
}
