<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\FcmChannel;
use App\Services\Finance\FinanceKeuanganMessageBody;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public bool $sendAppNotification = true,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $this->sendAppNotification) {
            return [];
        }

        return ['database', FcmChannel::class];
    }

    public function paymentVerifiedMessageLine(object $notifiable): string
    {
        return FinanceKeuanganMessageBody::paymentVerified($this->payment);
    }

    public function toArray(object $notifiable): array
    {
        $this->payment->loadMissing('invoice.student', 'invoice.payments');
        $invoice = $this->payment->invoice;
        $isPaidOff = $invoice?->status === \App\Models\Invoice::STATUS_PAID;
        $title = $isPaidOff ? 'Tagihan Lunas' : 'Pembayaran Cicilan Diterima';
        $body = $this->compactInAppBody($notifiable);
        $roleTarget = $this->roleTarget($notifiable);
        $url = $invoice?->id
            ? '/'.$roleTarget.'/invoices/'.(string) $invoice->id
            : '/'.$roleTarget.'/invoices';

        return [
            'type' => 'payment_verified',
            'title' => $title,
            'body' => $body,
            'message' => $body,
            'url' => $url,
            'entity_type' => 'invoice',
            'entity_id' => (string) ($invoice?->id ?? ''),
            'role_target' => $roleTarget,
            'priority' => 'p0',
            'collapse_key' => 'payment_verified_'.(string) $this->payment->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $invoice = $this->payment->invoice;
        $this->payment->loadMissing('invoice.student', 'invoice.payments');
        $isPaidOff = $invoice?->status === \App\Models\Invoice::STATUS_PAID;
        $title = $isPaidOff ? 'Tagihan Lunas' : 'Pembayaran Cicilan Diterima';
        $body = $this->compactInAppBody($notifiable);
        $roleTarget = $this->roleTarget($notifiable);
        $url = $invoice?->id
            ? '/'.$roleTarget.'/invoices/'.(string) $invoice->id
            : '/'.$roleTarget.'/invoices';

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'payment_verified',
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'entity_type' => 'invoice',
                'entity_id' => (string) ($invoice?->id ?? ''),
                'role_target' => $roleTarget,
                'priority' => 'p0',
                'collapse_key' => 'payment_verified_'.(string) $this->payment->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function roleTarget(object $notifiable): string
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('santri')) {
            return 'santri';
        }

        return 'wali';
    }

    private function compactInAppBody(object $_notifiable): string
    {
        $this->payment->loadMissing('invoice.student', 'invoice.payments');
        $invoice = $this->payment->invoice;
        $student = $invoice?->student;
        $remaining = $invoice ? $invoice->remainingAmount() : 0;
        $amountText = number_format((float) $this->payment->amount, 0, ',', '.');
        $remainingText = number_format((float) $remaining, 0, ',', '.');

        return 'Pembayaran '.($this->payment->payment_number ?? '').' untuk '.($student?->full_name ?? 'santri').' sebesar Rp '.$amountText.' terverifikasi. Sisa tagihan Rp '.$remainingText.'.';
    }
}
