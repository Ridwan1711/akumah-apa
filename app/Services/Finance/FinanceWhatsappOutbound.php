<?php

namespace App\Services\Finance;

use App\Jobs\SendFinanceWhatsappMessageJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\InvoiceReminderNotification;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Support\Facades\Config;

final class FinanceWhatsappOutbound
{
    public function __construct(
        private readonly FinanceWhatsappRecipient $recipient,
    ) {}

    public function queueInvoiceReminderToPhone(
        string $normalizedPhone,
        Invoice $invoice,
        InvoiceReminderNotification $notification,
        int $staggerIndex,
    ): void {
        if (! Config::boolean('services.wa.enabled')) {
            return;
        }

        $this->queueRaw(
            $normalizedPhone,
            $notification->reminderMessageText(),
            'invoice_reminder',
            (int) $invoice->id,
            $staggerIndex,
        );
    }

    /**
     * Satu job per guardian ber-akun; dedupe per nomor tujuan untuk payment yang sama.
     */
    public function queuePaymentVerifiedForPayment(Payment $payment): int
    {
        if (! Config::boolean('services.wa.enabled')) {
            return 0;
        }

        $payment->loadMissing('invoice.student.user', 'invoice.student.guardians.user');
        $student = $payment->invoice?->student;
        if (! $student instanceof Student) {
            return 0;
        }

        /** @var array<string, true> $dedupe */
        $dedupe = [];
        $stagger = 0;
        $queued = 0;

        foreach ($student->guardians as $guardian) {
            $waliUser = $guardian->user;
            if (! $waliUser instanceof User) {
                continue;
            }

            $phone = $this->recipient->resolve($student, $waliUser);
            if ($phone === null) {
                continue;
            }

            $key = (string) $payment->id.'|'.$phone;
            if (isset($dedupe[$key])) {
                continue;
            }

            $dedupe[$key] = true;
            $notification = new PaymentVerifiedNotification($payment, true);
            $this->queueRaw(
                $phone,
                $notification->paymentVerifiedMessageLine($waliUser),
                'payment_verified',
                $payment->invoice_id !== null ? (int) $payment->invoice_id : null,
                $stagger,
            );
            $stagger++;
            $queued++;
        }

        return $queued;
    }

    private function queueRaw(string $phone, string $text, string $context, ?int $invoiceId, int $staggerIndex): void
    {
        $interval = max(0, (int) config('services.wa.bulk_delay_seconds', 12));
        $delaySeconds = max(0, $staggerIndex) * $interval;

        SendFinanceWhatsappMessageJob::dispatch($phone, $text, $context, $invoiceId)
            ->onQueue((string) config('services.wa.queue', 'wa'))
            ->delay(now()->addSeconds($delaySeconds));
    }
}
