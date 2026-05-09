<?php

namespace App\Services\Finance;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;

final class FinanceWhatsappRecipient
{
    /**
     * Urutan: whatsapp santri (user) → nomor wali spesifik / guardian pertama → fallback ENV (opsional).
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

    private function guardianDigits(Guardian $guardian): ?string
    {
        if ($guardian->without_phone) {
            return null;
        }

        return FinanceWhatsappPhone::normalize($guardian->phone);
    }
}
