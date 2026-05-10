<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\StudentDiscount;
use App\Notifications\InvoiceCreatedNotification;
use InvalidArgumentException;

class InvoiceImportSupport
{
    public static function studentMasterDiscount(int $studentId, int $paymentTypeId, int $academicYearId, float $amount): float
    {
        $discount = StudentDiscount::query()
            ->where('student_id', $studentId)
            ->where('payment_type_id', $paymentTypeId)
            ->where('academic_year_id', $academicYearId)
            ->first();

        return $discount ? $discount->calculateDiscount($amount) : 0.0;
    }

    /**
     * Strict breakdown: override rows must sum to amount, or use payment type template scaled to amount.
     *
     * @return array<int, array{label:string, amount:float}>
     */
    public static function resolveBreakdownForInvoice(PaymentType $paymentType, float $amount, mixed $overrideBreakdown): array
    {
        $normalized = PaymentType::normalizeBreakdownItems($overrideBreakdown);
        $resolved = $normalized !== [] ? $normalized : $paymentType->buildBreakdownForAmount($amount);

        if ($resolved === []) {
            return [];
        }

        $sum = PaymentType::breakdownTotal($resolved);
        if (abs($sum - round($amount, 2)) > 0.01) {
            throw new InvalidArgumentException('Total rincian harus sama dengan nominal tagihan.');
        }

        return $resolved;
    }

    /**
     * Bulk-style: scale override template to match amount when override sum is positive.
     *
     * @return array<int, array{label:string, amount:float}>
     */
    public static function resolveBreakdownScaled(PaymentType $paymentType, float $amount, mixed $overrideBreakdown): array
    {
        $normalizedOverride = PaymentType::normalizeBreakdownItems($overrideBreakdown);
        if ($normalizedOverride !== []) {
            $sum = PaymentType::breakdownTotal($normalizedOverride);
            if ($sum > 0) {
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
        }

        return $paymentType->buildBreakdownForAmount($amount);
    }

    public static function notifyInvoiceCreatedTargets(Invoice $invoice): void
    {
        $invoice->loadMissing('student.user', 'student.guardians.user');
        $student = $invoice->student;
        if (! $student) {
            return;
        }

        $student->guardians
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn ($user) => $user->notify(new InvoiceCreatedNotification($invoice)));

        if ($student->user) {
            $student->user->notify(new InvoiceCreatedNotification($invoice));
        }
    }
}
