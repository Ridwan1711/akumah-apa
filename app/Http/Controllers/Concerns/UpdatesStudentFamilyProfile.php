<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Student;
use App\Services\StudentFamilyProfileSyncService;
use App\Support\GuardianProfileRules;
use Illuminate\Http\Request;

trait UpdatesStudentFamilyProfile
{
    protected function familySyncService(): StudentFamilyProfileSyncService
    {
        return app(StudentFamilyProfileSyncService::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateStudentProfileUpdate(Request $request): array
    {
        return $request->validate(GuardianProfileRules::profileUpdateRules());
    }

    protected function applyStudentProfileUpdate(Student $student, array $validated): void
    {
        $familyMerged = null;

        if (isset($validated['parents']) && is_array($validated['parents'])) {
            $addresses = is_array($validated['addresses'] ?? null) ? $validated['addresses'] : [];
            $familyMerged = $this->familySyncService()->sync($student, $validated['parents'], $addresses);
        }

        $incoming = is_array($validated['em_profile'] ?? null) ? $validated['em_profile'] : [];
        if ($incoming !== [] || $familyMerged !== null) {
            $this->familySyncService()->upsertEmProfile($student, $incoming, $familyMerged);
        }

        if (array_key_exists('full_name', $validated)) {
            $student->full_name = $validated['full_name'] ?? $student->full_name;
        }
        if (array_key_exists('nik', $validated)) {
            $student->nik = $validated['nik'];
        }
        if (array_key_exists('birth_place', $validated)) {
            $student->birth_place = $validated['birth_place'];
        }
        if (array_key_exists('birth_date', $validated)) {
            $student->birth_date = $validated['birth_date'];
        }
        if (array_key_exists('gender', $validated)) {
            $student->gender = $validated['gender'];
        }
        if (array_key_exists('address', $validated)) {
            $student->address = $validated['address'];
        }
        $student->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function studentProfilePayload(Student $student): array
    {
        $payload = $student->toArray();
        $emProfile = $student->emProfilePayloadForFrontend() ?: [
            'santri' => [],
            'alamat' => [],
        ];
        $payload['em_profile'] = $this->familySyncService()->mergeGuardiansIntoPayload($student, $emProfile);
        $payload['family_profile_complete'] = $student->hasRequiredGuardians();
        $payload['em_profile_complete'] = ($student->emisProfile?->isSantriDataComplete() ?? false)
            && $student->hasRequiredGuardians();

        return $payload;
    }
}
