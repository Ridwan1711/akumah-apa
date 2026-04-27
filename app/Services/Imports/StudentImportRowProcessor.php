<?php

namespace App\Services\Imports;

use App\Models\Student;
use Illuminate\Support\Facades\Validator;

class StudentImportRowProcessor
{
    public function process(array $data, string $strategy): array
    {
        // $data['gender'] = $this->resolveGender($data);

        $validator = Validator::make($data, [
            'nis' => ['required', 'string', 'max:20'],
            'full_name' => ['required', 'string', 'max:255'],
            'admission_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'gender' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first()];
        }

        $existing = Student::query()->where('nis', $data['nis'])->first();

        if ($existing && $strategy === 'skip') {
            if (($existing->gender ?? null) !== $data['gender']) {
                return [
                    'status' => 'failed',
                    'message' => "NIS {$data['nis']} sudah ada dengan gender {$existing->gender}. Ubah strategi ke update untuk sinkronkan gender.",
                ];
            }

            return ['status' => 'skipped', 'message' => null];
        }

        $payload = [
            'nis' => $data['nis'],
            'full_name' => $data['full_name'],
            'admission_year' => (int) $data['admission_year'],
            'gender' => $data['gender'],
            'status' => Student::STATUS_ACTIVE,
        ];
        // debug
        \Log::info('payload', [$payload]);
        if ($existing) {
            $existing->update($payload);

            return ['status' => 'updated', 'message' => null];
        }

        Student::create($payload);

        return ['status' => 'created', 'message' => null];
    }

    protected function normalizeGender(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));
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

    protected function resolveGender(array $data): ?string
    {
        return $this->normalizeGender(
            $data['gender']
            ?? $data['gender_lp']
            ?? $data['gender_l_p']
            ?? $data['jenis_kelamin']
            ?? $data['jk']
            ?? null
        );
    }
}
