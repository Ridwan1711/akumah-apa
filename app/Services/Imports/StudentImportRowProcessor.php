<?php

namespace App\Services\Imports;

use App\Models\Student;
use Illuminate\Support\Facades\Validator;

class StudentImportRowProcessor
{
    public function process(array $data, string $strategy): array
    {
        $validator = Validator::make($data, [
            'full_name' => ['required', 'string', 'max:255'],
            'admission_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'gender' => ['nullable'],
            'nis' => ['nullable', 'string', 'max:20'],
            'nik' => ['nullable', 'string', 'max:16'],
        ]);

        if ($validator->fails()) {
            return ['status' => 'failed', 'message' => $validator->errors()->first()];
        }

        $gender = $this->resolveGender($data) ?? Student::GENDER_MALE;

        ['student' => $existing, 'error' => $resolveError] = Student::resolveImportDuplicateStudent($data);
        if ($resolveError !== null) {
            return ['status' => 'failed', 'message' => $resolveError];
        }

        $nikFromRow = isset($data['nik']) ? trim((string) $data['nik']) : '';

        if ($existing && $strategy === 'skip') {
            if (($existing->gender ?? null) !== $gender) {
                return [
                    'status' => 'failed',
                    'message' => "Santri teridentifikasi ({$existing->nis}) sudah ada dengan gender {$existing->gender}. Ubah strategi ke update untuk sinkronkan gender.",
                ];
            }

            return ['status' => 'skipped', 'message' => null];
        }

        $payload = [
            'full_name' => trim((string) $data['full_name']),
            'admission_year' => (int) $data['admission_year'],
            'gender' => $gender,
            'status' => Student::STATUS_ACTIVE,
        ];
        if ($nikFromRow !== '') {
            $payload['nik'] = $nikFromRow;
        }

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
