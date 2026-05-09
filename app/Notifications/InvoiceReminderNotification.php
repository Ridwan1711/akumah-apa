<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\FcmChannel;
use App\Services\Finance\FinanceKeuanganMessageBody;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public ?string $customMessage = null,
        public ?int $sentByUserId = null,
        public bool $sendAppNotification = true,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $this->sendAppNotification) {
            return [];
        }

        return ['database', FcmChannel::class];
    }

    public function reminderMessageText(): string
    {
        return $this->body();
    }

    public function toArray(object $notifiable): array
    {
        $body = $this->body();
        $url = '/wali/invoices/'.(string) $this->invoice->id;

        return [
            'type' => 'invoice_reminder',
            'title' => 'Pengingat Tagihan',
            'body' => $body,
            'message' => $body,
            'url' => $url,
            'entity_type' => 'invoice',
            'entity_id' => (string) $this->invoice->id,
            'role_target' => 'wali',
            'priority' => 'p0',
            'collapse_key' => 'invoice_reminder_'.(string) $this->invoice->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
            'sent_by_user_id' => $this->sentByUserId,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $body = $this->body();
        $url = '/wali/invoices/'.(string) $this->invoice->id;

        return [
            'title' => 'Pengingat Tagihan',
            'body' => $body,
            'data' => [
                'type' => 'invoice_reminder',
                'title' => 'Pengingat Tagihan',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'invoice',
                'entity_id' => (string) $this->invoice->id,
                'role_target' => 'wali',
                'priority' => 'p0',
                'collapse_key' => 'invoice_reminder_'.(string) $this->invoice->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
                'sent_by_user_id' => (string) ($this->sentByUserId ?? 0),
            ],
        ];
    }

    private function body(): string
    {
        return FinanceKeuanganMessageBody::invoiceReminder($this->invoice, $this->customMessage);
    }
}
