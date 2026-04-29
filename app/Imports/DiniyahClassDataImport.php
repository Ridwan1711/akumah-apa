<?php

namespace App\Imports;

use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Services\SystemLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DiniyahClassDataImport implements ToCollection, WithHeadingRow
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

            $gradeLevelId = $this->resolveGradeLevelId($data);
            if ($gradeLevelId === null) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Jenjang tidak ditemukan. Isi grade_level_id atau grade_level_name yang valid.',
                ];
                continue;
            }

            $validator = Validator::make(
                [
                    ...$data,
                    'grade_level_id' => $gradeLevelId,
                ],
                [
                    'name' => ['required', 'string', 'max:100'],
                    'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
                    'order' => ['required', 'integer', 'min:0', 'max:1000'],
                    ...SchoolClass::studentGenderValidationRules(required: true),
                ]
            );

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => $validator->errors()->first(),
                ];
                app(SystemLogService::class)->error($validator->errors()->first());
                continue;
            }

            $payload = [
                'name' => $data['name'],
                'grade_level_id' => $gradeLevelId,
                'order' => (int) $data['order'],
                'student_gender' => $data['student_gender'],
            ];

            $existing = SchoolClass::query()->where('name', $data['name'])->first();

            if ($existing && $this->strategy === 'skip') {
                $this->skipped++;
                continue;
            }

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                SchoolClass::query()->create($payload);
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

    protected function resolveGradeLevelId(array $data): ?int
    {
        if ($data['grade_level_id'] !== null) {
            $gradeLevel = GradeLevel::query()->find((int) $data['grade_level_id']);

            return $gradeLevel?->id;
        }

        if ($data['grade_level_name'] !== null) {
            $gradeLevel = GradeLevel::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['grade_level_name'])])
                ->first();

            return $gradeLevel?->id;
        }

        return null;
    }

    protected function normalizeRow(array $row): array
    {
        return [
            'name' => $this->string($row['name'] ?? null),
            'grade_level_id' => $this->string($row['grade_level_id'] ?? null),
            'grade_level_name' => $this->string($row['grade_level_name'] ?? null),
            'order' => $this->string($row['order'] ?? null),
            'student_gender' => strtoupper((string) $this->string($row['student_gender'] ?? null)),
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

    protected function nullableString(mixed $value): ?string
    {
        return $this->string($value);
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
