<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchInvoiceRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<int, int>  $invoiceIds
     */
    public function __construct(
        public array $invoiceIds,
        public ?string $customMessage = null,
        public ?int $sentByUserId = null,
    ) {}

    public function handle(): void
    {
        foreach (array_chunk($this->invoiceIds, 200) as $invoiceIdChunk) {
            $invoices = Invoice::query()
                ->whereIn('id', $invoiceIdChunk)
                ->with([
                    'paymentType:id,name',
                    'student:id,full_name',
                    'student.guardians.user',
                ])
                ->get();

            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                $guardianUsers = $invoice->student?->guardians?->pluck('user')->filter()->unique('id') ?? collect();

                foreach ($guardianUsers as $guardianUser) {
                    $guardianUser->notify(new InvoiceReminderNotification($invoice, $this->customMessage, $this->sentByUserId));
                }

                if ($guardianUsers->isNotEmpty()) {
                    $invoice->forceFill([
                        'last_reminder_sent_at' => now(),
                        'reminder_count' => ((int) $invoice->reminder_count) + 1,
                    ])->saveQuietly();
                }
            }
        }
    }
}
