<?php

namespace App\Imports;

use App\Models\Diniyyah\GradeLevel;
use App\Models\Diniyyah\SchoolClass;
use App\Services\SystemLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

            $existingByName = SchoolClass::query()->where('name', $data['name'])->first();

            if ($existingByName && $this->strategy === 'skip') {
                $this->skipped++;

                continue;
            }

            $this->processed++;

            $gradeLevelId = $this->resolveGradeLevelId($data);
            if ($gradeLevelId === null) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Jenjang tidak ditemukan. Isi grade_level_id (angka ID) atau grade_level_name (nama jenjang) yang sesuai data master.',
                ];
                continue;
            }

            $orderRules = ['required', 'integer', 'min:0', 'max:1000'];
            $orderRules[] = $existingByName
                ? Rule::unique('classes', 'order')->ignore($existingByName->id)
                : Rule::unique('classes', 'order');

            $validator = Validator::make(
                [
                    ...$data,
                    'grade_level_id' => $gradeLevelId,
                ],
                [
                    'name' => ['required', 'string', 'max:100'],
                    'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
                    'order' => $orderRules,
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

            if ($existingByName) {
                $existingByName->update($payload);
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
        if ($data['grade_level_id'] !== null && ctype_digit((string) $data['grade_level_id'])) {
            $byId = GradeLevel::query()->find((int) $data['grade_level_id']);
            if ($byId) {
                return (int) $byId->id;
            }
        }

        if ($data['grade_level_name'] !== null) {
            $byName = GradeLevel::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($data['grade_level_name']))])
                ->first();

            return $byName?->id;
        }

        return null;
    }

    /**
     * Samakan kunci header Excel (bermacam-macam label / slug) ke kolom kanonik impor.
     *
     * @param  array<string|int, mixed>  $row
     * @return array{name: ?string, grade_level_id: ?string, grade_level_name: ?string, order: ?string, student_gender: ?string}
     */
    protected function normalizeRow(array $row): array
    {
        $canonical = [
            'name' => null,
            'grade_level_id' => null,
            'grade_level_name' => null,
            'order' => null,
            'student_gender' => null,
        ];

        foreach ($row as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $canonicalKey = $this->mapHeaderToField($key);
            if ($canonicalKey === null) {
                continue;
            }

            if ($canonicalKey === 'student_gender') {
                $canonical[$canonicalKey] = $this->normalizeStudentGenderCode($this->string($value));
            } else {
                $canonical[$canonicalKey] = $this->string($value);
            }
        }

        return $canonical;
    }

    protected function mapHeaderToField(string $header): ?string
    {
        $k = str_replace([' ', '-', "\t"], '_', strtolower(trim($header)));

        return match ($k) {
            'name', 'nama', 'nama_kelas', 'kelas', 'class_name' => 'name',
            'grade_level_id', 'id_jenjang', 'jenjang_id', 'id_grade_level' => 'grade_level_id',
            'grade_level_name', 'nama_jenjang', 'jenjang', 'grade_level' => 'grade_level_name',
            'order', 'urutan', 'sort', 'no_urut' => 'order',
            'student_gender', 'jenis_santri', 'gender_santri', 'gender', 'jk' => 'student_gender',
            default => in_array($k, ['name', 'grade_level_id', 'grade_level_name', 'order', 'student_gender'], true)
                ? $k
                : null,
        };
    }

    protected function normalizeStudentGenderCode(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $g = strtoupper(trim($raw));

        return match ($g) {
            'M', 'L', 'IKHWAN', 'SANTRIYYIN', 'PUTRA', 'LAKI', 'LAKI_LAKI' => SchoolClass::STUDENT_GENDER_SANTRIYYIN,
            'F', 'P', 'AKHWAT', 'SANTRIYYAH', 'PUTRI', 'PEREMPUAN' => SchoolClass::STUDENT_GENDER_SANTRIYYAH,
            default => strlen($g) === 1 ? $g : null,
        };
    }

    protected function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array{name: ?string, grade_level_id: ?string, grade_level_name: ?string, order: ?string, student_gender: ?string}  $row
     */
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
