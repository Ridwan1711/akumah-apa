<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;

class CancelExpiredGatewayPayments extends Command
{
    protected $signature = 'payments:cancel-expired-gateway';

    protected $description = 'Reject expired pending gateway payments and recalculate invoice status';

    public function handle(): int
    {
        $expiredPayments = Payment::query()
            ->where('payment_method', Payment::METHOD_GATEWAY)
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('gateway_expiry_time')
            ->where('gateway_expiry_time', '<', now())
            ->get();

        $updated = 0;
        $invoiceIds = [];
        foreach ($expiredPayments as $payment) {
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'notes' => 'Gateway status: expire (scheduler)',
                'verified_at' => now(),
            ]);
            $invoiceIds[] = (int) $payment->invoice_id;
            $updated++;
        }

        $invoiceIds = array_values(array_unique($invoiceIds));
        if ($invoiceIds !== []) {
            Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->get()
                ->each(fn (Invoice $invoice) => $invoice->recalculateStatus());
        }

        $this->info("Rejected {$updated} expired gateway payments.");

        return self::SUCCESS;
    }
}
