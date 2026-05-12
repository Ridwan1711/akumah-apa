<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentDataImport implements ToCollection, WithHeadingRow
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
                'full_name' => ['required', 'string', 'max:255'],
                'admission_year' => ['required', 'integer', 'min:2000', 'max:2099'],
                'gender' => ['required', 'in:'.Student::GENDER_MALE.','.Student::GENDER_FEMALE],
                'nis' => ['nullable', 'string', 'max:20'],
                'nik' => ['nullable', 'string', 'max:16'],
            ]);

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            ['student' => $existing, 'error' => $resolveError] = Student::resolveImportDuplicateStudent($data);
            if ($resolveError !== null) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $resolveError,
                ];

                continue;
            }

            $nikFromRow = isset($data['nik']) ? trim((string) $data['nik']) : '';

            $payload = [
                'full_name' => $data['full_name'],
                'admission_year' => (int) $data['admission_year'],
                'gender' => $data['gender'],
                'status' => Student::STATUS_ACTIVE,
            ];
            if ($nikFromRow !== '') {
                $payload['nik'] = $nikFromRow;
            }

            if ($existing && $this->strategy === 'skip') {
                $this->skipped++;

                continue;
            }

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                Student::create($payload);
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
            'nis' => $this->string($row['nis'] ?? null),
            'nik' => $this->string($row['nik'] ?? null),
            'full_name' => $this->string($row['full_name'] ?? null),
            'admission_year' => $this->intOrNull($row['admission_year'] ?? null),
            'gender' => $this->normalizeGender($row['gender'] ?? null),
        ];
    }

    protected function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    protected function normalizeGender(mixed $value): ?string
    {
        $normalized = strtoupper((string) $this->string($value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['L', 'LK', 'LAKI-LAKI', 'LAKI LAKI'], true)) {
            return Student::GENDER_MALE;
        }

        if (in_array($normalized, ['P', 'PR', 'PEREMPUAN'], true)) {
            return Student::GENDER_FEMALE;
        }

        return $normalized;
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
