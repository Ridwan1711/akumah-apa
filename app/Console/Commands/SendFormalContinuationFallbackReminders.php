<?php

namespace App\Console\Commands;

use App\Services\FormalContinuationService;
use Illuminate\Console\Command;

class SendFormalContinuationFallbackReminders extends Command
{
    protected $signature = 'formal-continuation:send-fallback';

    protected $description = 'Kirim undangan konfirmasi lanjut formal (MTs 9 / MA 12) otomatis 2 bulan sebelum TA berakhir jika belum dikirim admin';

    public function handle(FormalContinuationService $service): int
    {
        $result = $service->runFallbackTwoMonthsBeforeEnd();

        $this->info("Tahun ajaran diproses: {$result['processed']}, round baru: {$result['rounds']}.");

        return self::SUCCESS;
    }
}
