<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Mark pending invoices as overdue if past due date';

    public function handle(): int
    {
        $count = Invoice::where('status', Invoice::STATUS_PENDING)
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => Invoice::STATUS_OVERDUE]);

        $this->info("Marked {$count} invoices as overdue.");

        return self::SUCCESS;
    }
}
