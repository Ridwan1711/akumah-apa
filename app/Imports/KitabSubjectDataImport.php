<?php

namespace App\Imports;

use App\Models\Diniyyah\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KitabSubjectDataImport implements ToCollection, WithHeadingRow
{
    protected int $processed = 0;

    protected int $created = 0;

    protected int $updated = 0;

    protected int $skipped = 0;

    protected array $errors = [];

    public function __construct(
        protected string $strategy = 'skip'
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $this->processed++;

            $validator = Validator::make($data, [
                'name' => ['required', 'string', 'max:100'],
            ]);

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            $existing = Subject::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
                ->first();

            if ($existing && $this->strategy === 'skip') {
                $this->skipped++;

                continue;
            }

            if ($existing) {
                $existing->update(['name' => $data['name']]);
                $this->updated++;
            } else {
                Subject::query()->create(['name' => $data['name']]);
                $this->created++;
            }
        }
    }

    public function result(): array
    {
        return [
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => count($this->errors),
            'errors' => $this->errors,
        ];
    }

    protected function normalizeRow(array $row): array
    {
        return [
            'name' => $this->string($row['name'] ?? null),
        ];
    }

    protected function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
