<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    private const EVENT_TYPE = 'invoice_due_soon';

    public function toArray(object $notifiable): array
    {
        $student = $this->invoice->student;

        return [
            'type' => self::EVENT_TYPE,
            'title' => 'Tagihan Jatuh Tempo',
            'body' => 'Tagihan '.($this->invoice->invoice_number ?? '').' untuk '.($student?->full_name ?? 'santri').' telah jatuh tempo.',
            'message' => 'Tagihan '.($this->invoice->invoice_number ?? '').' untuk '.($student?->full_name ?? 'santri').' telah jatuh tempo.',
            'url' => '/wali/invoices/'.(string) $this->invoice->id,
            'entity_type' => 'invoice',
            'entity_id' => (string) $this->invoice->id,
            'role_target' => 'wali',
            'priority' => 'p0',
            'collapse_key' => 'invoice_due_soon_'.(string) $this->invoice->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        $student = $this->invoice->student;
        $body = 'Tagihan '.($this->invoice->invoice_number ?? '').' untuk '.($student?->full_name ?? 'santri').' telah jatuh tempo.';
        $url = '/wali/invoices/'.(string) $this->invoice->id;

        return [
            'title' => 'Tagihan Jatuh Tempo',
            'body' => $body,
            'data' => [
                'type' => self::EVENT_TYPE,
                'title' => 'Tagihan Jatuh Tempo',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'invoice',
                'entity_id' => (string) $this->invoice->id,
                'role_target' => 'wali',
                'priority' => 'p0',
                'collapse_key' => 'invoice_due_soon_'.(string) $this->invoice->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }
}
