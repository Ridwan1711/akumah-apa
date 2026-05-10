<?php

namespace App\Services\Finance;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;

final class FinanceWhatsappRecipient
{
    /**
     * Urutan: whatsapp di akun user santri → no_hp di em_profile santri → nomor wali / guardian → fallback ENV (opsional).
     *
     * @param  ?User  $waliUser  Akun wali yang sedang di-notify (untuk memilih baris guardian yang cocok).
     * @param  ?Guardian  $onlyGuardian  Jika set, setelah santri gunakan nomor guardian ini (untuk wali tanpa akun).
     */
    public function resolve(Student $student, ?User $waliUser = null, ?Guardian $onlyGuardian = null): ?string
    {
        $student->loadMissing('user:id,whatsapp_phone', 'guardians');

        $fromSantri = FinanceWhatsappPhone::normalize($student->user?->whatsapp_phone);
        if ($fromSantri !== null) {
            return $fromSantri;
        }

        $fromEmProfile = $this->studentEmProfileNoHp($student);
        if ($fromEmProfile !== null) {
            return $fromEmProfile;
        }

        if ($onlyGuardian !== null) {
            return $this->guardianDigits($onlyGuardian);
        }

        if ($waliUser !== null) {
            foreach ($student->guardians as $guardian) {
                if ((int) $guardian->user_id === (int) $waliUser->id) {
                    $phone = $this->guardianDigits($guardian);
                    if ($phone !== null) {
                        return $phone;
                    }

                    break;
                }
            }
        }

        foreach ($student->guardians as $guardian) {
            $phone = $this->guardianDigits($guardian);
            if ($phone !== null) {
                return $phone;
            }
        }

        if (config('services.wa.allow_fallback')) {
            return FinanceWhatsappPhone::normalize((string) config('services.wa.fallback_phone'));
        }

        return null;
    }

    private function studentEmProfileNoHp(Student $student): ?string
    {
        $profile = $student->em_profile;
        if (! is_array($profile)) {
            return null;
        }

        $raw = $profile['no_hp'] ?? null;
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        return FinanceWhatsappPhone::normalize(is_scalar($raw) ? (string) $raw : null);
    }

    private function guardianDigits(Guardian $guardian): ?string
    {
        if ($guardian->without_phone) {
            return null;
        }

        return FinanceWhatsappPhone::normalize($guardian->phone);
    }
}
