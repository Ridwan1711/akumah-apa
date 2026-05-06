<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $invoice = $this->payment->invoice;
        $student = $invoice?->student;
        $roleTarget = $this->roleTarget($notifiable);
        $url = $invoice?->id
            ? '/'.$roleTarget.'/invoices/'.(string) $invoice->id
            : '/'.$roleTarget.'/invoices';

        return [
            'type' => 'payment_verified',
            'title' => 'Pembayaran Terverifikasi',
            'body' => 'Pembayaran '.($this->payment->payment_number ?? '').' untuk '.($student?->full_name ?? 'santri').' telah diverifikasi.',
            'message' => 'Pembayaran '.($this->payment->payment_number ?? '').' untuk '.($student?->full_name ?? 'santri').' telah diverifikasi.',
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
        $student = $invoice?->student;
        $roleTarget = $this->roleTarget($notifiable);
        $url = $invoice?->id
            ? '/'.$roleTarget.'/invoices/'.(string) $invoice->id
            : '/'.$roleTarget.'/invoices';
        $body = 'Pembayaran '.($this->payment->payment_number ?? '').' untuk '.($student?->full_name ?? 'santri').' telah diverifikasi.';

        return [
            'title' => 'Pembayaran Terverifikasi',
            'body' => $body,
            'data' => [
                'type' => 'payment_verified',
                'title' => 'Pembayaran Terverifikasi',
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
}
