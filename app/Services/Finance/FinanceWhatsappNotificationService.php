<?php

namespace App\Services\Finance;

use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Channels\WhatsappChannel;
use App\Notifications\InvoiceReminderNotification;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class FinanceWhatsappNotificationService
{
    public function __construct(
        private readonly FinanceWhatsappRecipient $recipient,
    ) {}

    public function notifyInvoiceReminder(
        Invoice $invoice,
        Student $student,
        InvoiceReminderNotification $notification,
        ?User $waliUser,
        ?Guardian $guardianWithoutUser,
        int $staggerIndex,
    ): bool {
        if (! config('services.wa.enabled')) {
            return false;
        }

        $phone = $guardianWithoutUser !== null
            ? $this->recipient->resolve($student, null, $guardianWithoutUser)
            : $this->recipient->resolve($student, $waliUser);

        if ($phone === null) {
            Log::warning('invoice_wa_skipped_no_phone', [
                'invoice_id' => (int) $invoice->id,
                'student_id' => (int) $student->id,
            ]);

            return false;
        }

        $waNotification = new InvoiceReminderNotification(
            $notification->invoice,
            $notification->customMessage,
            $notification->sentByUserId,
            false,
            true,
            $phone,
        );

        $this->dispatchWa($waNotification, $waliUser, $phone, $staggerIndex);

        return true;
    }

    /**
     * Kirim notifikasi app + WhatsApp ke wali yang punya akun (dedupe per nomor WA).
     */
    public function notifyPaymentVerified(Payment $payment): int
    {
        $payment->loadMissing('invoice.student.user', 'invoice.student.guardians.user');
        $student = $payment->invoice?->student;
        if (! $student instanceof Student) {
            return 0;
        }

        $waEnabled = (bool) config('services.wa.enabled');
        /** @var array<string, true> $dedupe */
        $dedupe = [];
        $stagger = 0;
        $notified = 0;

        foreach ($student->guardians as $guardian) {
            $waliUser = $guardian->user;
            if (! $waliUser instanceof User) {
                continue;
            }

            $phone = $waEnabled ? $this->recipient->resolve($student, $waliUser) : null;
            $sendWhatsapp = $phone !== null;

            if ($sendWhatsapp) {
                $key = (string) $payment->id.'|'.$phone;
                if (isset($dedupe[$key])) {
                    $waliUser->notify(new PaymentVerifiedNotification($payment, true, false));

                    continue;
                }
                $dedupe[$key] = true;
            }

            $notification = new PaymentVerifiedNotification($payment, true, $sendWhatsapp, $phone);

            if ($sendWhatsapp) {
                $interval = max(0, (int) config('services.wa.bulk_delay_seconds', 12));
                $queueName = (string) config('services.wa.queue', 'wa');
                $waliUser->notify(
                    $notification
                        ->delay(now()->addSeconds($stagger * $interval))
                        ->onQueue($queueName),
                );
                $stagger++;
            } else {
                $waliUser->notify($notification);
            }

            $notified++;
        }

        return $notified;
    }

    private function dispatchWa(
        InvoiceReminderNotification $notification,
        ?User $waliUser,
        string $phone,
        int $staggerIndex,
    ): void {
        $interval = max(0, (int) config('services.wa.bulk_delay_seconds', 12));
        $delaySeconds = max(0, $staggerIndex) * $interval;
        $queueName = (string) config('services.wa.queue', 'wa');

        $pending = $notification
            ->delay(now()->addSeconds($delaySeconds))
            ->onQueue($queueName);

        if ($waliUser instanceof User) {
            $waliUser->notify($pending);

            return;
        }

        $anonymous = (new AnonymousNotifiable)->route(WhatsappChannel::class, $phone);
        Notification::send($anonymous, $pending);
    }
}
