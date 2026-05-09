<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Finance\DispatchInvoiceRemindersAction;
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
        public bool $sendAppNotification = true,
        public bool $sendWhatsapp = false,
    ) {}

    public function handle(DispatchInvoiceRemindersAction $action): void
    {
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
