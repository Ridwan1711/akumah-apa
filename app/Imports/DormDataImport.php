<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DormDataImport implements WithMultipleSheets
{
    private readonly DormImportResult $aggregate;

    public function __construct(
        private readonly string $strategy,
        private readonly string $placementStrategy = 'skip',
    ) {
        $this->aggregate = new DormImportResult;
    }

    public function sheets(): array
    {
        return [
            'Gedung_Kamar' => new DormGedungKamarSheetImport($this->strategy, $this->aggregate),
            'Penempatan' => new DormPenempatanSheetImport($this->aggregate, $this->placementStrategy),
        ];
    }

    /**
     * @return array{processed:int, created:int, updated:int, skipped:int, failed:int, errors: array<int, array{row: int|string, message: string}>}
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
