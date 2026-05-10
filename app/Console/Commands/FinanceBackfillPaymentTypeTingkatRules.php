<?php

namespace App\Console\Commands;

use App\Models\PaymentType;
use App\Models\PaymentTypeTingkatSekolahRule;
use App\Models\TingkatSekolah;
use Illuminate\Console\Command;

class FinanceBackfillPaymentTypeTingkatRules extends Command
{
    protected $signature = 'finance:backfill-payment-type-tingkat-rules {--force : Timpa aturan yang sudah ada}';

    protected $description = 'Isi tabel payment_type_tingkat_sekolah_rules dari default_amount / default_breakdown dan kuliah_amount';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $kuliahId = TingkatSekolah::query()->where('code', TingkatSekolah::CODE_KULIAH)->value('id');

        foreach (PaymentType::query()->get() as $paymentType) {
            foreach (TingkatSekolah::query()->orderBy('order')->get() as $tingkat) {
                $exists = PaymentTypeTingkatSekolahRule::query()
                    ->where('payment_type_id', $paymentType->id)
                    ->where('tingkat_sekolah_id', $tingkat->id)
                    ->exists();

                if ($exists && ! $force) {
                    continue;
                }

                $isKuliah = $kuliahId !== null && (int) $tingkat->id === (int) $kuliahId;
                if ($isKuliah) {
                    $amount = $paymentType->kuliah_amount;
                    $enabled = $amount !== null && (float) $amount > 0;
                    $breakdown = $enabled ? $paymentType->normalizedDefaultBreakdown() : [];

                    if ($enabled && $breakdown !== []) {
                        $breakdown = $paymentType->breakdownForAmountWithTemplate((float) $amount, $breakdown);
                    }
                } else {
                    $amount = $paymentType->default_amount;
                    $enabled = (float) $amount > 0;
                    $breakdown = $enabled ? $paymentType->normalizedDefaultBreakdown() : [];
                    if ($enabled && $breakdown !== []) {
                        $breakdown = $paymentType->breakdownForAmountWithTemplate((float) $amount, $breakdown);
                    }
                }

                PaymentTypeTingkatSekolahRule::query()->updateOrCreate(
                    [
                        'payment_type_id' => $paymentType->id,
                        'tingkat_sekolah_id' => $tingkat->id,
                    ],
                    [
                        'is_enabled' => $enabled,
                        'amount' => $enabled ? $amount : null,
                        'breakdown' => $breakdown === [] ? null : $breakdown,
                    ]
                );
            }
        }

        $this->info('Selesai mem-backfill aturan tingkat formal per jenis pembayaran.');

        return self::SUCCESS;
    }
}
