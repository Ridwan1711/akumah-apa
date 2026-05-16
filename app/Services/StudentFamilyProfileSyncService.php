<?php

namespace App\Services;

use App\Models\EmProfile;
use App\Models\Guardian;
use App\Models\Student;
use App\Support\GuardianProfileRules;

class StudentFamilyProfileSyncService
{
    /**
     * Sinkronkan guardians + meta + snapshot alamat di em_profile.
     *
     * @param  array<string, mixed>  $parents
     * @param  array<string, mixed>  $addresses
     * @return array<string, mixed>  Payload em_profile lengkap (santri + alamat) untuk disimpan
     */
    public function sync(Student $student, array $parents, array $addresses): array
    {
        $student->loadMissing(['guardians' => fn ($q) => $q->withPivot('relationship')]);

        $waliSource = $parents['wali_data_source'] ?? 'manual';
        $ayahData = GuardianProfileRules::sanitizeDeceasedGuardian($parents['ayah'] ?? []);
        $ibuData = GuardianProfileRules::sanitizeDeceasedGuardian($parents['ibu'] ?? []);

        $waliInput = match ($waliSource) {
            'ayah' => $ayahData,
            'ibu' => $ibuData,
            default => GuardianProfileRules::sanitizeDeceasedGuardian($parents['wali'] ?? []),
        };

        $ayahAddress = $this->normalizeAddressInput($addresses['ayah'] ?? []);
        $ibuAddress = $this->normalizeAddressInput(
            ($addresses['ibu_sama_dengan_ayah'] ?? false)
                ? ($addresses['ayah'] ?? [])
                : ($addresses['ibu'] ?? [])
        );

        $waliAddressSource = $addresses['wali'] ?? [];
        if ($addresses['wali_sama_dengan_ayah'] ?? false) {
            $waliAddressSource = $addresses['ayah'] ?? [];
        } elseif ($addresses['wali_sama_dengan_ibu'] ?? false) {
            $waliAddressSource = ($addresses['ibu_sama_dengan_ayah'] ?? false)
                ? ($addresses['ayah'] ?? [])
                : ($addresses['ibu'] ?? []);
        }
        $waliAddress = $this->normalizeAddressInput($waliAddressSource);
        $santriAddress = $this->normalizeAddressInput($addresses['santri'] ?? []);

        $this->upsertGuardian($student, 'ayah', $ayahData, $ayahAddress);
        $this->upsertGuardian($student, 'ibu', $ibuData, $ibuAddress);
        $this->upsertGuardian($student, 'wali', $waliInput, $waliAddress);

        $currentPayload = $student->emProfilePayload();
        $meta = is_array($currentPayload['_meta'] ?? null) ? $currentPayload['_meta'] : [];
        $meta['wali_data_source'] = $waliSource;
        $meta['ibu_sama_dengan_ayah'] = (bool) ($addresses['ibu_sama_dengan_ayah'] ?? false);
        $meta['wali_sama_dengan_ayah'] = (bool) ($addresses['wali_sama_dengan_ayah'] ?? false);
        $meta['wali_sama_dengan_ibu'] = (bool) ($addresses['wali_sama_dengan_ibu'] ?? false);

        $merged = array_replace_recursive($currentPayload, [
            '_meta' => $meta,
            'alamat' => [
                'ayah' => $ayahAddress,
                'ibu' => $ibuAddress,
                'wali' => $waliAddress,
                'santri' => $santriAddress,
            ],
        ]);

        $student->unsetRelation('guardians');
        $student->load(['guardians' => fn ($q) => $q->withPivot('relationship')]);

        return $this->mergeGuardiansIntoPayload($student, $merged);
    }

    /**
     * Gabungkan data guardians ke payload em_profile untuk frontend.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mergeGuardiansIntoPayload(Student $student, array $payload): array
    {
        $student->loadMissing(['guardians' => fn ($q) => $q->withPivot('relationship')]);

        $byRel = [];
        foreach ($student->guardians as $guardian) {
            $rel = $guardian->pivot->relationship ?? $guardian->relationship;
            if (is_string($rel) && $rel !== '') {
                $byRel[$rel] = $guardian;
            }
        }

        $alamat = is_array($payload['alamat'] ?? null) ? $payload['alamat'] : [];

        foreach (['ayah', 'ibu', 'wali'] as $rel) {
            if (! isset($byRel[$rel])) {
                continue;
            }
            $alamat[$rel] = array_replace(
                $this->guardianToAddressPayload($byRel[$rel]),
                is_array($alamat[$rel] ?? null) ? $alamat[$rel] : []
            );
        }

        $payload['alamat'] = $alamat;

        if (! isset($payload['_meta']) || ! is_array($payload['_meta'])) {
            $payload['_meta'] = [];
        }

        if (! isset($payload['_meta']['wali_data_source'])) {
            $payload['_meta']['wali_data_source'] = $this->inferWaliDataSource(
                $byRel['ayah'] ?? null,
                $byRel['ibu'] ?? null,
                $byRel['wali'] ?? null,
            );
        }

        return $payload;
    }

    public function inferWaliDataSource(?Guardian $ayah, ?Guardian $ibu, ?Guardian $wali): string
    {
        if ($wali === null) {
            return 'manual';
        }

        if ($ayah !== null && $this->guardiansMatchIdentity($ayah, $wali)) {
            return 'ayah';
        }

        if ($ibu !== null && $this->guardiansMatchIdentity($ibu, $wali)) {
            return 'ibu';
        }

        return 'manual';
    }

    public function guardianToParentPayload(Guardian $guardian): array
    {
        return [
            'id' => $guardian->id,
            'full_name' => $guardian->full_name,
            'status' => $guardian->status === 'wafat' ? 'wafat' : ($guardian->status ?: 'hidup'),
            'kewarganegaraan' => $guardian->kewarganegaraan,
            'nik' => $guardian->nik,
            'birth_place' => $guardian->birth_place,
            'birth_date' => $guardian->birth_date?->toDateString(),
            'last_education' => $guardian->last_education,
            'occupation' => $guardian->occupation,
            'monthly_income' => $guardian->monthly_income,
            'income_band' => $guardian->income_band,
            'phone' => $guardian->phone,
            'without_phone' => (bool) $guardian->without_phone,
            'email' => $guardian->email,
            'no_kks' => $guardian->no_kks,
            'no_pkh' => $guardian->no_pkh,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function guardianToAddressPayload(Guardian $guardian): array
    {
        $base = [
            'sama_dengan_ktp' => false,
            'provinsi' => $guardian->provinsi,
            'provinsi_code' => $guardian->provinsi_code,
            'kabupaten' => $guardian->kabupaten,
            'kabupaten_code' => $guardian->kabupaten_code,
            'kecamatan' => $guardian->kecamatan,
            'kecamatan_code' => $guardian->kecamatan_code,
            'kelurahan' => $guardian->kelurahan,
            'kelurahan_code' => $guardian->kelurahan_code,
            'dusun' => $guardian->dusun,
            'rw' => $guardian->rw,
            'rt' => $guardian->rt,
            'alamat' => $guardian->alamat,
            'kode_pos' => $guardian->kode_pos,
            'nik_ktp' => $guardian->nik_ktp,
        ];

        if (in_array($guardian->pivot->relationship ?? $guardian->relationship, ['ayah', 'ibu'], true)) {
            $base['tinggal_luar_negeri'] = (bool) $guardian->tinggal_luar_negeri;
            $base['status_kepemilikan_rumah'] = $guardian->status_kepemilikan_rumah;
        }

        if (($guardian->pivot->relationship ?? $guardian->relationship) === 'wali') {
            $base['domisili'] = $guardian->domisili;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function normalizeAddressInput(array $source): array
    {
        return [
            'tinggal_luar_negeri' => (bool) ($source['tinggal_luar_negeri'] ?? false),
            'status_kepemilikan_rumah' => $source['status_kepemilikan_rumah'] ?? null,
            'sama_dengan_ktp' => (bool) ($source['sama_dengan_ktp'] ?? false),
            'provinsi' => $source['provinsi'] ?? null,
            'provinsi_code' => $source['provinsi_code'] ?? null,
            'kabupaten' => $source['kabupaten'] ?? null,
            'kabupaten_code' => $source['kabupaten_code'] ?? null,
            'kecamatan' => $source['kecamatan'] ?? null,
            'kecamatan_code' => $source['kecamatan_code'] ?? null,
            'kelurahan' => $source['kelurahan'] ?? null,
            'kelurahan_code' => $source['kelurahan_code'] ?? null,
            'dusun' => $source['dusun'] ?? ($source['kampung'] ?? null),
            'rw' => $source['rw'] ?? null,
            'rt' => $source['rt'] ?? null,
            'alamat' => $source['alamat'] ?? null,
            'kode_pos' => $source['kode_pos'] ?? null,
            'nik_ktp' => $source['nik_ktp'] ?? null,
            'domisili' => $source['domisili'] ?? null,
            'jarak_tempat_tinggal_lembaga' => $source['jarak_tempat_tinggal_lembaga'] ?? null,
            'transportasi_ke_lembaga' => $source['transportasi_ke_lembaga'] ?? null,
            'koordinat' => $source['koordinat'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $address
     */
    private function upsertGuardian(Student $student, string $relationship, array $data, array $address): void
    {
        $existing = $student->guardians->first(
            fn (Guardian $g) => ($g->pivot->relationship ?? $g->relationship) === $relationship
        );

        $payload = $this->guardianPayload($student->id, $relationship, $data, $address, $existing?->user_id);

        if ($existing) {
            $existing->update($payload);
            $student->guardians()->syncWithoutDetaching([
                $existing->id => ['relationship' => $relationship],
            ]);
        } else {
            $guardian = Guardian::create($payload);
            $student->guardians()->syncWithoutDetaching([
                $guardian->id => ['relationship' => $relationship],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function guardianPayload(
        int $studentId,
        string $relationship,
        array $data,
        array $address,
        ?int $userId = null,
    ): array {
        return [
            'student_id' => $studentId,
            'user_id' => $userId,
            'relationship' => $relationship,
            'status' => $data['status'] ?? null,
            'full_name' => $data['full_name'] ?? null,
            'nik' => $data['nik'] ?? null,
            'kewarganegaraan' => $data['kewarganegaraan'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $data['phone'] ?? null,
            'without_phone' => (bool) ($data['without_phone'] ?? false),
            'email' => $data['email'] ?? null,
            'last_education' => $data['last_education'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'income_band' => $data['income_band'] ?? null,
            'monthly_income' => $data['monthly_income'] ?? null,
            'no_kks' => $data['no_kks'] ?? null,
            'no_pkh' => $data['no_pkh'] ?? null,
            'tinggal_luar_negeri' => (bool) ($address['tinggal_luar_negeri'] ?? false),
            'status_kepemilikan_rumah' => $address['status_kepemilikan_rumah'] ?? null,
            'domisili' => $address['domisili'] ?? null,
            'provinsi' => $address['provinsi'] ?? null,
            'provinsi_code' => $address['provinsi_code'] ?? null,
            'kabupaten' => $address['kabupaten'] ?? null,
            'kabupaten_code' => $address['kabupaten_code'] ?? null,
            'kecamatan' => $address['kecamatan'] ?? null,
            'kecamatan_code' => $address['kecamatan_code'] ?? null,
            'kelurahan' => $address['kelurahan'] ?? null,
            'kelurahan_code' => $address['kelurahan_code'] ?? null,
            'dusun' => $address['dusun'] ?? null,
            'rw' => $address['rw'] ?? null,
            'rt' => $address['rt'] ?? null,
            'alamat' => $address['alamat'] ?? null,
            'kode_pos' => $address['kode_pos'] ?? null,
            'nik_ktp' => $address['nik_ktp'] ?? null,
        ];
    }

    private function guardiansMatchIdentity(Guardian $a, Guardian $b): bool
    {
        $nameA = mb_strtolower(trim((string) $a->full_name));
        $nameB = mb_strtolower(trim((string) $b->full_name));
        if ($nameA === '' || $nameB === '' || $nameA !== $nameB) {
            return false;
        }

        $nikA = trim((string) ($a->nik ?? ''));
        $nikB = trim((string) ($b->nik ?? ''));

        if ($nikA !== '' && $nikB !== '') {
            return $nikA === $nikB;
        }

        return true;
    }

    /**
     * Persist em_profiles row + students.em_profile JSON from merged payload.
     *
     * @param  array<string, mixed>  $incomingPartial  Partial em_profile (e.g. only santri)
     * @param  array<string, mixed>|null  $familyMerged  Full merge after family sync
     */
    public function upsertEmProfile(Student $student, array $incomingPartial, ?array $familyMerged = null): void
    {
        $student->loadMissing('emisProfile');
        $current = $student->emProfilePayload();

        if ($familyMerged !== null) {
            $merged = array_replace_recursive($familyMerged, $incomingPartial);
        } else {
            $merged = array_replace_recursive($current, $incomingPartial);
        }

        $merged = $student->withComputedNismInEmProfilePayload($merged);
        $merged = $this->mergeGuardiansIntoPayload($student, $merged);

        $attributes = EmProfile::fromPayload($merged);
        $student->emisProfile()->updateOrCreate([], $attributes);
        $student->forceFill(['em_profile' => $merged])->save();
        $student->unsetRelation('emisProfile');
        $student->load('emisProfile');
    }
}
