<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\WhatsappChannel;
use App\Notifications\Messages\WhatsappMessage;
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
        public bool $sendWhatsapp = false,
        public ?string $whatsappOverridePhone = null,
    ) {
        $this->onQueue((string) config('services.wa.queue', 'wa'));
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->sendAppNotification) {
            $channels[] = 'database';
            $channels[] = FcmChannel::class;
        }

        if ($this->sendWhatsapp && config('services.wa.enabled')) {
            $channels[] = WhatsappChannel::class;
        }

        return $channels;
    }

    public function paymentVerifiedMessageLine(object $notifiable): string
    {
        return FinanceKeuanganMessageBody::paymentVerified($this->payment);
    }

    public function toWhatsapp(object $notifiable): ?WhatsappMessage
    {
        if (! $this->sendWhatsapp || $this->whatsappOverridePhone === null) {
            return null;
        }

        return WhatsappMessage::make(
            FinanceKeuanganMessageBody::paymentVerified($this->payment),
            $this->whatsappOverridePhone,
            'payment_verified',
        );
    }

    public function toArray(object $notifiable): array
    {
        $this->payment->loadMissing('invoice.student', 'invoice.payments');
        $invoice = $this->payment->invoice;
        $isPaidOff = $invoice?->status === Invoice::STATUS_PAID;
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
        $isPaidOff = $invoice?->status === Invoice::STATUS_PAID;
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
