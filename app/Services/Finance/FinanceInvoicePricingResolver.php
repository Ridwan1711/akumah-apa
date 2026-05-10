<?php

namespace App\Services\Finance;

use App\Models\EnrollmentTingkatSekolah;
use App\Models\PaymentType;
use App\Models\PaymentTypeTingkatSekolahRule;
use App\Models\Student;
use App\Models\TingkatSekolah;

/**
 * Resolve nominal tagihan per santri untuk tahun ajaran tertentu
 * menggunakan tingkat sekolah formal (bukan kelas diniyyah).
 */
final class FinanceInvoicePricingResolver
{
    /**
     * @return array{
     *   tingkat_sekolah_id: ?int,
     *   amount: ?float,
     *   skip_reason: ?string
     * }
     */
    public function resolve(PaymentType $paymentType, Student $student, int $academicYearId): array
    {
        $enrollment = EnrollmentTingkatSekolah::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->with('tingkatSekolah')
            ->first();

        if ($enrollment instanceof EnrollmentTingkatSekolah && $enrollment->tingkatSekolah) {
            return $this->resolveWithFormalTingkat($paymentType, $enrollment);
        }

        return $this->resolveLegacyFallback($paymentType, $student);
    }

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    public function resolveBreakdown(
        PaymentType $paymentType,
        float $amount,
        ?PaymentTypeTingkatSekolahRule $appliedRule,
        mixed $bulkOverrideBreakdown,
        bool $scaleBulkOverride
    ): array {
        $normalizedOverride = PaymentType::normalizeBreakdownItems($bulkOverrideBreakdown);
        if ($normalizedOverride !== []) {
            return $scaleBulkOverride
                ? $this->scaleOverrideToAmount($normalizedOverride, $amount)
                : PaymentType::scaleNormalizedBreakdownToAmount($normalizedOverride, $amount);
        }

        $templateRows = $appliedRule?->normalizedBreakdown();

        return $paymentType->breakdownForAmountWithTemplate($amount, $templateRows ?? []);
    }

    /**
     * @param  array<int, array{label:string, amount:float}>  $normalizedOverride
     * @return array<int, array{label:string, amount:float}>
     */
    private function scaleOverrideToAmount(array $normalizedOverride, float $amount): array
    {
        $sum = PaymentType::breakdownTotal($normalizedOverride);
        if ($sum <= 0) {
            return [];
        }
        $ratio = $amount / $sum;
        $allocated = 0.0;
        $scaled = [];
        foreach ($normalizedOverride as $index => $item) {
            $isLast = $index === count($normalizedOverride) - 1;
            $itemAmount = $isLast
                ? round($amount - $allocated, 2)
                : round($item['amount'] * $ratio, 2);
            $itemAmount = max(0, $itemAmount);
            $allocated += $itemAmount;
            $scaled[] = [
                'label' => $item['label'],
                'amount' => $itemAmount,
            ];
        }

        return PaymentType::normalizeBreakdownItems($scaled);
    }

    /**
     * @return array{
     *   tingkat_sekolah_id: ?int,
     *   amount: ?float,
     *   applied_rule: ?PaymentTypeTingkatSekolahRule,
     *   skip_reason: ?string,
     * }
     */
    public function resolveAmountAndRule(PaymentType $paymentType, Student $student, int $academicYearId): array
    {
        $base = $this->resolve($paymentType, $student, $academicYearId);

        $rule = null;
        if ($base['tingkat_sekolah_id'] !== null) {
            $rule = PaymentTypeTingkatSekolahRule::query()
                ->where('payment_type_id', $paymentType->id)
                ->where('tingkat_sekolah_id', $base['tingkat_sekolah_id'])
                ->first();
        }

        return [
            'tingkat_sekolah_id' => $base['tingkat_sekolah_id'],
            'amount' => $base['amount'],
            'applied_rule' => $rule,
            'skip_reason' => $base['skip_reason'],
        ];
    }

    private function resolveWithFormalTingkat(PaymentType $paymentType, EnrollmentTingkatSekolah $enrollment): array
    {
        $tingkatId = (int) $enrollment->tingkat_sekolah_id;
        $rule = PaymentTypeTingkatSekolahRule::query()
            ->where('payment_type_id', $paymentType->id)
            ->where('tingkat_sekolah_id', $tingkatId)
            ->first();

        if ($rule) {
            if (! $rule->is_enabled || $rule->amount === null) {
                return [
                    'tingkat_sekolah_id' => $tingkatId,
                    'amount' => null,
                    'skip_reason' => 'Tarif tingkat ini dinonaktifkan atau kosong untuk jenis pembayaran tersebut.',
                ];
            }

            return [
                'tingkat_sekolah_id' => $tingkatId,
                'amount' => round((float) $rule->amount, 2),
                'skip_reason' => null,
            ];
        }

        return [
            'tingkat_sekolah_id' => $tingkatId,
            'amount' => round((float) $paymentType->default_amount, 2),
            'skip_reason' => null,
        ];
    }

    /**
     * Akun santri tanpa enrollment formal masih dikenakan pola lama (default_amount / kuliah_amount).
     */
    private function resolveLegacyFallback(PaymentType $paymentType, Student $student): array
    {
        $kuliahTingkatId = TingkatSekolah::query()
            ->where('code', TingkatSekolah::CODE_KULIAH)
            ->value('id');

        if ($student->is_kuliah) {
            if ($paymentType->kuliah_amount === null) {
                return [
                    'tingkat_sekolah_id' => $kuliahTingkatId !== null ? (int) $kuliahTingkatId : null,
                    'amount' => null,
                    'skip_reason' => 'Legacy: santri kuliah tanpa tarif kuliah pada jenis pembayaran ini.',
                ];
            }

            return [
                'tingkat_sekolah_id' => $kuliahTingkatId !== null ? (int) $kuliahTingkatId : null,
                'amount' => round((float) $paymentType->kuliah_amount, 2),
                'skip_reason' => null,
            ];
        }

        return [
            'tingkat_sekolah_id' => null,
            'amount' => round((float) $paymentType->default_amount, 2),
            'skip_reason' => null,
        ];
    }
}
