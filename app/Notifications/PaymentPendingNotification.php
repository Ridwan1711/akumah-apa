<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentPendingNotification extends Notification implements ShouldQueue
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
        $this->payment->loadMissing('invoice.student', 'invoice.payments');
        $invoice = $this->payment->invoice;
        $student = $invoice?->student;
        $amountText = number_format((float) $this->payment->amount, 0, ',', '.');
        $estimatedRemaining = max(0, (float) ($invoice?->remainingAmount() ?? 0) - (float) $this->payment->amount);
        $remainingText = number_format($estimatedRemaining, 0, ',', '.');
        $body = 'Bukti pembayaran '.($this->payment->payment_number ?? '').' untuk '.($student?->full_name ?? 'santri').' sebesar Rp '.$amountText.' menunggu verifikasi. Estimasi sisa setelah verifikasi Rp '.$remainingText.'.';

        return [
            'type' => 'payment_pending',
            'title' => 'Pembayaran Menunggu Verifikasi',
            'body' => $body,
            'message' => $body,
            'url' => '/admin/payments',
            'entity_type' => 'payment',
            'entity_id' => (string) $this->payment->id,
            'role_target' => 'admin',
            'priority' => 'p0',
            'collapse_key' => 'payment_pending_'.(string) $this->payment->id,
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
        $amountText = number_format((float) $this->payment->amount, 0, ',', '.');
        $estimatedRemaining = max(0, (float) ($invoice?->remainingAmount() ?? 0) - (float) $this->payment->amount);
        $remainingText = number_format($estimatedRemaining, 0, ',', '.');
        $body = 'Bukti pembayaran '.($this->payment->payment_number ?? '').' untuk '.($student?->full_name ?? 'santri').' sebesar Rp '.$amountText.' menunggu verifikasi. Estimasi sisa setelah verifikasi Rp '.$remainingText.'.';

        return [
            'title' => 'Pembayaran Menunggu Verifikasi',
            'body' => $body,
            'data' => [
                'type' => 'payment_pending',
                'title' => 'Pembayaran Menunggu Verifikasi',
                'body' => $body,
                'url' => '/admin/payments',
                'entity_type' => 'payment',
                'entity_id' => (string) $this->payment->id,
                'role_target' => 'admin',
                'priority' => 'p0',
                'collapse_key' => 'payment_pending_'.(string) $this->payment->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }
}
