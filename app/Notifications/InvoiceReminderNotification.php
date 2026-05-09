<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\FcmChannel;
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
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
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
        if ($this->customMessage !== null && trim($this->customMessage) !== '') {
            return trim($this->customMessage);
        }

        $invoiceNumber = (string) ($this->invoice->invoice_number ?? '-');
        $paymentTypeName = (string) ($this->invoice->paymentType?->name ?? 'Tagihan');
        $remaining = max(0, (float) $this->invoice->remainingAmount());
        $remainingFormatted = number_format($remaining, 0, ',', '.');
        $dueDate = $this->invoice->due_date?->format('d-m-Y') ?? '-';

        return "Tagihan {$invoiceNumber} ({$paymentTypeName}) sebesar Rp {$remainingFormatted} jatuh tempo {$dueDate}. Mohon segera diselesaikan.";
    }
}
