<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class InstallmentService
{
    public function validateAmount(
        Invoice $invoice,
        float $amount,
        bool $allowExceedRemaining = false,
        bool $useEffectiveRemaining = false,
    ): void {
        $remaining = $useEffectiveRemaining ? $invoice->effectiveRemaining() : $invoice->remainingAmount();
        if ($remaining <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Tagihan sudah lunas.',
            ]);
        }

        $minInstallment = (float) config('payment.min_installment_amount', 10000);
        if ($amount < $minInstallment) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal cicilan minimal Rp ' . number_format($minInstallment, 0, ',', '.'),
            ]);
        }

        if (! $allowExceedRemaining && $amount > ($remaining + 0.00001)) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal melebihi sisa tagihan.',
            ]);
        }
    }

    public function cancelStalePendingGatewayPayments(Invoice $invoice): int
    {
        $updated = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_method', Payment::METHOD_GATEWAY)
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNotNull('gateway_expiry_time')
                    ->where('gateway_expiry_time', '<', now());
            })
            ->update([
                'status' => Payment::STATUS_REJECTED,
                'notes' => 'Gateway status: expire (auto-cancel)',
                'verified_at' => now(),
            ]);

        if ($updated > 0) {
            $invoice->recalculateStatus();
        }

        return $updated;
    }

    public function assertNoActivePendingGateway(Invoice $invoice): void
    {
        $activePending = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_method', Payment::METHOD_GATEWAY)
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('gateway_expiry_time')
                    ->orWhere('gateway_expiry_time', '>', now());
            })
            ->latest('id')
            ->first();

        if (! $activePending) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'error' => 'Masih ada pembayaran gateway yang aktif untuk tagihan ini.',
            'active_pending_gateway' => [
                'payment_id' => (int) $activePending->id,
                'payment_number' => (string) $activePending->payment_number,
                'amount' => (float) $activePending->amount,
                'gateway_order_id' => (string) $activePending->gateway_order_id,
                'gateway_expiry_time' => $activePending->gateway_expiry_time?->toIso8601String(),
                'gateway_va_number' => $activePending->gateway_va_number,
                'gateway_qr_url' => $activePending->gateway_qr_url,
            ],
        ], 422));
    }
}
