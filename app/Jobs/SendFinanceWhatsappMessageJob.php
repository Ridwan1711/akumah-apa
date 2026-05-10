<?php

namespace App\Jobs;

use App\Services\Finance\NgedeployWaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFinanceWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $normalizedPhone,
        public string $message,
        public string $context,
        public ?int $invoiceId = null,
    ) {}

    public function handle(NgedeployWaClient $client): void
    {
        if (! config('services.wa.enabled')) {
            Log::warning('wa_send_skipped_job_wa_disabled', [
                'context' => $this->context,
                'invoice_id' => $this->invoiceId,
                'number_suffix' => NgedeployWaClient::maskNumber($this->normalizedPhone),
                'hint' => 'WA_ENABLED=false saat job jalan (atau config cache usang). Job tidak memanggil API.',
            ]);

            return;
        }

        Log::info('wa_send_attempt', [
            'context' => $this->context,
            'invoice_id' => $this->invoiceId,
            'number_suffix' => NgedeployWaClient::maskNumber($this->normalizedPhone),
        ]);

        $client->send($this->normalizedPhone, $this->message);

        Log::info('wa_send_success', [
            'context' => $this->context,
            'invoice_id' => $this->invoiceId,
            'number_suffix' => NgedeployWaClient::maskNumber($this->normalizedPhone),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('wa_send_job_failed_permanent', [
            'context' => $this->context,
            'invoice_id' => $this->invoiceId,
            'number_suffix' => NgedeployWaClient::maskNumber($this->normalizedPhone),
            'error' => $exception?->getMessage(),
            'exception_class' => $exception !== null ? $exception::class : null,
        ]);
    }
}
