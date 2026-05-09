<?php

namespace App\Services\Finance;

final class FinanceWhatsappPhone
{
    /**
     * Normalize ke digit internasional Indonesia (62…).
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return strlen($digits) >= 11 ? $digits : null;
        }

        if (str_starts_with($digits, '0')) {
            $rest = substr($digits, 1);

            return $rest !== '' ? '62'.$rest : null;
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return strlen($digits) >= 9 ? $digits : null;
    }
}
