<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Finance\DispatchInvoiceRemindersAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        public bool $sendAppNotification = true,
        public bool $sendWhatsapp = false,
    ) {}

    public function handle(DispatchInvoiceRemindersAction $action): void
    {
        Log::info('dispatch_invoice_reminders_job_started', [
            'invoice_ids_count' => count($this->invoiceIds),
            'send_app_notification' => $this->sendAppNotification,
            'send_whatsapp' => $this->sendWhatsapp,
            'wa_enabled' => (bool) config('services.wa.enabled'),
        ]);

        $waStaggerStart = 0;

        foreach (array_chunk($this->invoiceIds, 200) as $invoiceIdChunk) {
            $invoices = Invoice::query()
                ->whereIn('id', $invoiceIdChunk)
                ->with([
                    'paymentType:id,name',
                    'student:id,full_name,user_id',
                    'student.user:id,whatsapp_phone',
                    'student.guardians.user',
                ])
                ->get();

            $result = $action->run(
                $invoices,
                $this->customMessage,
                $this->sentByUserId,
                $this->sendAppNotification,
                $this->sendWhatsapp,
                $waStaggerStart,
            );

            $waStaggerStart += $result['wa_queued'];
        }
    }
}
