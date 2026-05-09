<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Notification;

class PaymentGatewayController extends Controller
{
    public const PAYMENT_METHOD_BRI_VA = 'bri_va';

    public const PAYMENT_METHOD_QRIS = 'qris';

    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$clientKey = config('midtrans.client_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public function createCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'payment_method' => ['required', 'in:bri_va,qris'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $invoice = Invoice::with(['student', 'paymentType'])->findOrFail($validated['invoice_id']);
        $installmentService = app(InstallmentService::class);

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])) {
            return response()->json(['error' => 'Tagihan sudah lunas atau dibatalkan.'], 422);
        }

        $installmentService->cancelStalePendingGatewayPayments($invoice);
        $installmentService->assertNoActivePendingGateway($invoice);

        $remaining = $invoice->remainingAmount();
        $amount = (float) ($validated['amount'] ?? $remaining);
        $installmentService->validateAmount($invoice, $amount);
        $orderId = 'INV-' . $invoice->id . '-' . time();
        $paymentMethod = $validated['payment_method'];

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber(),
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_method' => Payment::METHOD_GATEWAY,
            'payment_date' => now()->toDateString(),
            'gateway_order_id' => $orderId,
            'status' => Payment::STATUS_PENDING,
        ]);

        $baseParams = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'customer_details' => [
                'first_name' => $invoice->student->full_name,
                'email' => $invoice->student->user?->email ?? 'noemail@pesantren.id',
            ],
            'item_details' => [[
                'id' => (string) $invoice->payment_type_id,
                'price' => (int) $amount,
                'quantity' => 1,
                'name' => substr(
                    ($amount < $remaining ? 'Cicilan ' : '') . $invoice->paymentType->name . ' - ' . $invoice->invoice_number,
                    0,
                    50
                ),
            ]],
        ];

        try {
            if ($paymentMethod === self::PAYMENT_METHOD_BRI_VA) {
                $params = array_merge($baseParams, [
                    'payment_type' => 'bank_transfer',
                    'bank_transfer' => ['bank' => 'bri'],
                ]);
            } else {
                $params = array_merge($baseParams, [
                    'payment_type' => 'qris',
                    'qris' => ['acquirer' => 'gopay'],
                ]);
            }

            $chargeResult = CoreApi::charge($params);

            $charge = is_array($chargeResult) ? (object) $chargeResult : $chargeResult;
            $vaNumber = null;
            $bank = null;
            $qrString = null;
            $qrUrl = null;
            $expiryTime = $charge->expiry_time ?? null;

            if ($paymentMethod === self::PAYMENT_METHOD_BRI_VA) {
                $vaNumbers = $charge->va_numbers ?? [];
                $briVa = collect($vaNumbers)->first(fn ($v) => ($v->bank ?? $v['bank'] ?? null) === 'bri');
                if ($briVa) {
                    $vaNumber = $briVa->va_number ?? $briVa['va_number'] ?? null;
                    $bank = 'bri';
                }
            } else {
                $qrString = $charge->qr_string ?? null;
                $actions = $charge->actions ?? [];
                $qrAction = collect($actions)->first(fn ($a) => ($a->name ?? $a['name'] ?? null) === 'generate-qr-code');
                if ($qrAction) {
                    $qrUrl = $qrAction->url ?? $qrAction['url'] ?? null;
                }
            }

            $payment->update([
                'gateway_transaction_id' => $charge->transaction_id ?? null,
                'gateway_payment_type' => $paymentMethod,
                'gateway_va_number' => $vaNumber,
                'gateway_qr_url' => $qrUrl ?? $qrString,
                'gateway_expiry_time' => $expiryTime ? now()->parse($expiryTime) : null,
            ]);

            return response()->json([
                'payment_id' => $payment->id,
                'payment_method' => $paymentMethod,
                'va_number' => $vaNumber,
                'bank' => $bank,
                'qr_string' => $qrString,
                'qr_url' => $qrUrl,
                'expiry_time' => $expiryTime,
                'amount' => (int) $amount,
                'order_id' => $orderId,
            ]);
        } catch (\Exception $e) {
            $payment->update(['status' => Payment::STATUS_REJECTED, 'notes' => $e->getMessage()]);

            return response()->json(['error' => 'Gagal membuat transaksi: ' . $e->getMessage()], 500);
        }
    }

    public function handleNotification(Request $request): JsonResponse
    {
        try {
            $notification = new Notification();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid notification'], 400);
        }

        $orderId = $notification->order_id;
        $transactionStatus = $notification->transaction_status;
        $transactionId = $notification->transaction_id;
        $paymentType = $notification->payment_type;
        $fraudStatus = $notification->fraud_status ?? null;

        $payment = Payment::where('gateway_order_id', $orderId)->first();
        if (! $payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        $payment->gateway_transaction_id = $transactionId;
        $payment->gateway_payment_type = $paymentType;

        if ($transactionStatus === 'capture') {
            $payment->status = ($fraudStatus === 'accept') ? Payment::STATUS_VERIFIED : Payment::STATUS_PENDING;
        } elseif ($transactionStatus === 'settlement') {
            $payment->status = Payment::STATUS_VERIFIED;
            $payment->verified_at = now();
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $payment->status = Payment::STATUS_REJECTED;
            $payment->notes = "Gateway status: {$transactionStatus}";
        } elseif ($transactionStatus === 'pending') {
            $payment->status = Payment::STATUS_PENDING;
        }

        $payment->save();
        $payment->invoice->recalculateStatus();

        return response()->json(['status' => 'ok']);
    }
}
