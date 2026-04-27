<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $body = $this->body();

        return [
            'type' => 'invoice_created',
            'title' => 'Tagihan Baru',
            'body' => $body,
            'message' => $body,
            'url' => $url,
            'entity_type' => 'invoice',
            'entity_id' => (string) $this->invoice->id,
            'role_target' => $roleTarget,
            'priority' => 'p0',
            'collapse_key' => 'invoice_created_'.(string) $this->invoice->id,
            'sent_at' => now()->toIso8601String(),
            'notification_id' => (string) $this->id,
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toFcm(object $notifiable): array
    {
        [$url, $roleTarget] = $this->resolveUrlAndRole($notifiable);
        $body = $this->body();

        return [
            'title' => 'Tagihan Baru',
            'body' => $body,
            'data' => [
                'type' => 'invoice_created',
                'title' => 'Tagihan Baru',
                'body' => $body,
                'url' => $url,
                'entity_type' => 'invoice',
                'entity_id' => (string) $this->invoice->id,
                'role_target' => $roleTarget,
                'priority' => 'p0',
                'collapse_key' => 'invoice_created_'.(string) $this->invoice->id,
                'sent_at' => now()->toIso8601String(),
                'notification_id' => (string) $this->id,
            ],
        ];
    }

    private function body(): string
    {
        $invoiceNumber = $this->invoice->invoice_number ?? 'baru';

        return "Tagihan {$invoiceNumber} telah diterbitkan.";
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveUrlAndRole(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('santri')) {
            return ['/santri/invoices/'.(string) $this->invoice->id, 'santri'];
        }

        return ['/wali/invoices/'.(string) $this->invoice->id, 'wali'];
    }
}
