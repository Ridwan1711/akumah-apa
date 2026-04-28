<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PpdbStudentSyncService
{
    public function sync(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $applicant = is_array($payload['applicant'] ?? null) ? $payload['applicant'] : [];
            $guardians = is_array($payload['guardians'] ?? null) ? $payload['guardians'] : [];
            $applicationId = (int) ($payload['ppdb_application_id'] ?? 0);
            $regNo = isset($payload['reg_no']) ? (string) $payload['reg_no'] : null;

            $santriRoleId = (int) Role::query()->where('name', Role::SANTRI)->value('id');
            $waliRoleId = (int) Role::query()->where('name', Role::WALI_SANTRI)->value('id');
            abort_unless($santriRoleId > 0 && $waliRoleId > 0, 422, 'Role santri/wali_santri belum tersedia.');

            $generatedNis = $this->buildNis($payload, $applicant);

            $student = Student::query()
                ->where('ppdb_application_id', $applicationId)
                ->orWhere(function ($q) use ($regNo) {
                    if ($regNo) {
                        $q->where('ppdb_reg_no', $regNo);
                    }
                })
                ->first();

            if (! $student && $generatedNis !== '') {
                $student = Student::query()->where('nis', $generatedNis)->first();
            }

            if (! $student && ! empty($applicant['nik'])) {
                $student = Student::query()->where('nik', $applicant['nik'])->first();
            }

            $santriUsername = $this->resolveUniqueUsername($generatedNis !== '' ? $generatedNis : 'MH'.date('y').str_pad((string) $applicationId, 3, '0', STR_PAD_LEFT), $student?->user_id);
            $santriUser = $this->upsertStudentUser($student, $applicant, $santriUsername, $santriRoleId);

            $studentData = [
                'user_id' => $santriUser->id,
                'nis' => $generatedNis !== '' ? $generatedNis : $santriUsername,
                'nik' => $this->nullableString($applicant['nik'] ?? null),
                'full_name' => (string) ($applicant['full_name'] ?? 'Santri'),
                'birth_place' => $this->nullableString($applicant['birth_place'] ?? null),
                'birth_date' => $this->nullableString($applicant['birth_date'] ?? null),
                'gender' => in_array(($applicant['sex'] ?? ''), [Student::GENDER_MALE, Student::GENDER_FEMALE], true) ? $applicant['sex'] : Student::GENDER_MALE,
                'address' => $this->nullableString($applicant['address_line'] ?? null),
                'status' => Student::STATUS_ACTIVE,
                'admission_year' => (int) ($payload['intake']['year'] ?? date('Y')),
                'ppdb_application_id' => $applicationId,
                'ppdb_reg_no' => $regNo,
                'ppdb_synced_at' => now(),
            ];

            if ($student) {
                $student->update($studentData);
            } else {
                $student = Student::create($studentData);
            }

            $this->syncGuardians($student, $guardians, $waliRoleId);
            $this->syncApplicantProfileSnapshot($student, $payload, $applicant);

            return [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'santri_username' => $santriUser->username,
            ];
        });
    }

    private function syncGuardians(Student $student, array $guardians, int $waliRoleId): void
    {
        $pivot = [];

        foreach ($guardians as $guardianData) {
            if (! is_array($guardianData)) {
                continue;
            }

            $relationship = strtolower((string) ($guardianData['role'] ?? 'wali'));
            if (! in_array($relationship, Guardian::RELATIONSHIPS, true)) {
                $relationship = 'wali';
            }

            $guardian = Guardian::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'relationship' => $relationship,
                ],
                [
                    'full_name' => (string) ($guardianData['name'] ?? $guardianData['full_name'] ?? 'Wali'),
                    'nik' => $this->nullableString($guardianData['nik'] ?? null),
                    'phone' => $this->nullableString($guardianData['phone'] ?? null),
                    'email' => $this->nullableString($guardianData['email'] ?? null),
                    'occupation' => $this->nullableString($guardianData['occupation'] ?? null),
                    'last_education' => $this->nullableString($guardianData['education_level'] ?? null),
                    'income_band' => $this->nullableString($guardianData['income_band'] ?? null),
                    'monthly_income' => isset($guardianData['monthly_income']) ? (string) $guardianData['monthly_income'] : null,
                    'birth_place' => $this->nullableString($guardianData['birth_place'] ?? null),
                    'birth_date' => $this->nullableString($guardianData['birth_date'] ?? null),
                    'kewarganegaraan' => $this->nullableString($guardianData['kewarganegaraan'] ?? 'WNI'),
                    'alamat' => $this->nullableString($guardianData['address'] ?? null),
                ]
            );

            $pivot[$guardian->id] = ['relationship' => $relationship];

            if ($relationship === 'wali') {
                $waliUsername = $this->resolveUniqueUsername('WALI_'.$student->nis, $guardian->user_id);
                $waliUser = $this->upsertGuardianUser($guardian, $waliUsername, $waliRoleId);
                $guardian->update(['user_id' => $waliUser->id]);
            }
        }

        if ($pivot !== []) {
            $student->guardians()->syncWithoutDetaching($pivot);
        }
    }

    private function syncApplicantProfileSnapshot(Student $student, array $payload, array $applicant): void
    {
        $profilePayload = [
            'santri' => [
                'nisn' => $applicant['nisn'] ?? null,
                'nism' => $student->nis,
                'kewarganegaraan' => 'WNI',
                'agama' => 'Islam',
                'no_hp' => $applicant['phone'] ?? null,
                'email' => $applicant['email'] ?? null,
                'status_mukim' => 'mukim',
                'catatan_khusus' => 'Synced from PPDB application '.$payload['ppdb_application_id'],
            ],
            'alamat' => [
                'santri' => [
                    'alamat' => $applicant['address_line'] ?? null,
                ],
            ],
        ];

        // Sumber utama snapshot migrasi disimpan di kolom JSON student (lebih dekat ke pola Applicant PPDB).
        $student->forceFill([
            'em_profile' => $profilePayload,
        ])->save();

        // Tetap sinkronkan ke em_profiles bila relasinya tersedia, agar kompatibel fitur lama SIAKAD.
        if (method_exists($student, 'emisProfile')) {
            $student->emisProfile()->updateOrCreate([], \App\Models\EmProfile::fromPayload($profilePayload));
        }
    }

    private function upsertStudentUser(?Student $student, array $applicant, string $username, int $roleId): User
    {
        $user = $student?->user;
        if (! $user) {
            $user = User::create([
                'name' => (string) ($applicant['full_name'] ?? 'Santri'),
                'username' => $username,
                'email' => $this->buildDefaultEmail($username, 'santri'),
                'password' => Str::password(12),
                'is_active' => true,
                'must_change_password' => true,
                'must_complete_profile' => false,
            ]);
        } else {
            $user->update([
                'name' => (string) ($applicant['full_name'] ?? $user->name),
                'username' => $username,
                'email' => $this->nullableString($applicant['email'] ?? null) ?? $user->email,
            ]);
        }

        $user->roles()->syncWithoutDetaching([$roleId]);

        return $user;
    }

    private function upsertGuardianUser(Guardian $guardian, string $username, int $roleId): User
    {
        $user = $guardian->user;
        if (! $user) {
            $user = User::create([
                'name' => $guardian->full_name,
                'username' => $username,
                'email' => $guardian->email ?? $this->buildDefaultEmail($username, 'wali'),
                'password' => Str::password(12),
                'is_active' => true,
                'must_change_password' => true,
                'must_complete_profile' => false,
            ]);
        } else {
            $user->update([
                'name' => $guardian->full_name,
                'username' => $username,
                'email' => $guardian->email ?? $user->email,
            ]);
        }

        $user->roles()->syncWithoutDetaching([$roleId]);

        return $user;
    }

    private function resolveUniqueUsername(string $base, ?int $ignoreUserId = null): string
    {
        $candidate = strtoupper(trim($base));
        $suffix = 1;
        while (true) {
            $exists = User::query()
                ->where('username', $candidate)
                ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
                ->exists();
            if (! $exists) {
                return $candidate;
            }
            $candidate = strtoupper(trim($base)).'_'.$suffix;
            $suffix++;
        }
    }

    private function buildNis(array $payload, array $applicant): string
    {
        $year = (int) ($payload['intake']['year'] ?? date('Y'));
        $yearSuffix = substr((string) $year, -2);
        $sequence = (int) ($payload['registration_sequence'] ?? 0);
        if ($sequence <= 0) {
            $sequence = (int) ($payload['ppdb_application_id'] ?? 0);
        }
        $sequence = max($sequence, 1);
        $initials = $this->initials((string) ($applicant['full_name'] ?? 'S'));

        return 'MH'.$yearSuffix.str_pad((string) ($sequence % 1000), 3, '0', STR_PAD_LEFT).$initials;
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= strtoupper(substr($part, 0, 1));
            if (strlen($letters) >= 3) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'S';
    }

    private function buildDefaultEmail(string $username, string $domainPrefix): string
    {
        return strtolower($username).'@'.$domainPrefix.'.manhood.local';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
