<?php

namespace App\Services\Finance;

use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Menilai apakah periode tagihan (tahun ajaran + bulan berulang) jatuh setelah santri tidak aktif.
 *
 * Aturan: awal bulan yang ditagih (dalam rentang tahun ajaran) tidak boleh strictly setelah tanggal inactive_at.
 * Tagihan tanpa bulan: tahun ajaran dianggap "pasca keluar" hanya jika start_date TA strictly setelah inactive_at.
 */
final class StudentInvoiceCoverPeriod
{
    public static function userFacingMessage(): string
    {
        return 'Periode tagihan tidak boleh jatuh setelah tanggal tidak aktif santri (lulus/keluar/wafat). Sesuaikan bulan/tahun ajaran, atau perbarui tanggal tidak aktif di profil santri.';
    }

    public static function isPeriodAfterInactive(Student $student, AcademicYear $academicYear, ?int $month): bool
    {
        if ($student->status === Student::STATUS_ACTIVE) {
            return false;
        }

        if ($student->inactive_at === null) {
            return false;
        }

        $boundary = Carbon::parse($student->inactive_at)->startOfDay();

        if ($month !== null && $month >= 1 && $month <= 12) {
            $coverStart = self::firstDayOfBilledMonthWithinAcademicYear($academicYear, $month);
            $periodStart = $coverStart ?? Carbon::parse($academicYear->start_date)->startOfDay();

            return $periodStart->gt($boundary);
        }

        $ayStart = Carbon::parse($academicYear->start_date)->startOfDay();

        return $ayStart->gt($boundary);
    }

    /**
     * Hari pertama kalender bulan {@see $month} yang berada di dalam rentang tahun ajaran.
     */
    private static function firstDayOfBilledMonthWithinAcademicYear(AcademicYear $academicYear, int $month): ?Carbon
    {
        $start = Carbon::parse($academicYear->start_date)->startOfDay();
        $end = Carbon::parse($academicYear->end_date)->startOfDay();

        for ($y = $start->year; $y <= $end->year; $y++) {
            $som = Carbon::createFromDate($y, $month, 1)->startOfDay();
            if ($som->gte($start) && $som->lte($end)) {
                return $som;
            }
        }

        return null;
    }
}
