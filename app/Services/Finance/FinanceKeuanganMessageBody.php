<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonInterface;

/**
 * Teks panjang (WhatsApp / notifikasi) untuk reminder tagihan & pembayaran terverifikasi.
 */
final class FinanceKeuanganMessageBody
{
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function invoiceReminder(Invoice $invoice, ?string $customMessage): string
    {
        if ($customMessage !== null && trim($customMessage) !== '') {
            return trim($customMessage);
        }

        $invoice->loadMissing('paymentType:id,name', 'student:id,full_name', 'academicYear:id,name');

        $student = $invoice->student?->full_name ?? 'Santri';
        $remaining = max(0, (float) $invoice->remainingAmount());
        $remainingFmt = self::rp($remaining);

        $lines = [];
        $breakdown = $invoice->resolvedBreakdown();
        if ($breakdown !== []) {
            $n = 1;
            foreach ($breakdown as $row) {
                $label = trim((string) ($row['label'] ?? 'Item'));
                $amt = (float) ($row['amount'] ?? 0);
                $lines[] = $n.'. '.$label.' — '.self::rp($amt);
                $n++;
            }
        } else {
            $lines[] = '1. '.self::invoiceFallbackLineTitle($invoice).' — '.$remainingFmt;
        }

        $bodyLines = implode("\n", $lines);
        $appName = (string) config('app.name', 'Aplikasi');

        return <<<TXT
Assalamu'alaikum Warahmatullahi Wabarakatuh.

Sebagai pengingat, kami informasikan bahwa ananda *{$student}* masih memiliki tunggakan pembayaran sebagai berikut:

{$bodyLines}

**Total Tagihan: {$remainingFmt}**

Kami mohon kesediaannya untuk segera melakukan pembayaran, baik secara tunai maupun transfer. Pembayaran juga dapat dilakukan melalui aplikasi *{$appName}* yang tersedia di Play Store. Silakan login menggunakan akun Wali Santri/Santri yang telah kami sediakan.

Atas perhatian dan kerja samanya, kami ucapkan terima kasih.

Wassalamu'alaikum Warahmatullahi Wabarakatuh.
TXT;
    }

    public static function paymentVerified(Payment $payment): string
    {
        $payment->loadMissing([
            'invoice.student:id,full_name',
            'invoice.paymentType:id,name',
            'invoice.payments',
        ]);

        $invoice = $payment->invoice;
        if (! $invoice instanceof Invoice) {
            return 'Pembayaran terverifikasi.';
        }

        $student = $invoice->student?->full_name ?? 'Santri';
        $amountFmt = self::rp((float) $payment->amount);
        $receivedAt = self::formatIdDate($payment->verified_at ?? $payment->payment_date ?? now());

        $breakdown = $invoice->resolvedBreakdown();
        if ($breakdown === []) {
            $breakdown = [['label' => (string) ($invoice->paymentType?->name ?? 'Tagihan'), 'amount' => (float) $invoice->final_amount]];
        }

        $verifiedPayments = $invoice->payments
            ->where('status', Payment::STATUS_VERIFIED)
            ->sortBy(fn (Payment $p): string => sprintf(
                '%020d-%010d',
                (int) ($p->verified_at?->format('U') ?? 0),
                $p->id,
            ))
            ->values();

        $paidBefore = 0.0;
        foreach ($verifiedPayments as $p) {
            if ((int) $p->id === (int) $payment->id) {
                break;
            }
            $paidBefore += (float) $p->amount;
        }

        $allocations = self::fifoAllocateToBreakdown($breakdown, $paidBefore, (float) $payment->amount);
        $allocLines = [];
        foreach ($allocations as $row) {
            $suffix = $row['partial'] ? ' (Sebagian)' : '';
            $allocLines[] = '• '.$row['label'].' — '.self::rp($row['amount']).$suffix;
        }
        $allocBlock = $allocLines !== [] ? implode("\n", $allocLines) : '• '.($invoice->paymentType?->name ?? 'Tagihan').' — '.$amountFmt;

        $remaining = max(0, (float) $invoice->remainingAmount());
        $remainingFmt = self::rp($remaining);
        $isPaidOff = $invoice->status === Invoice::STATUS_PAID;

        $tail = "Kami ucapkan terima kasih atas kerja sama dan kepercayaan yang telah diberikan.\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh.";

        if ($isPaidOff || $remaining <= 0.009) {
            return <<<TXT
Assalamu'alaikum Warahmatullahi Wabarakatuh.

Alhamdulillah, pembayaran sebesar {$amountFmt} atas nama ananda *{$student}* telah kami terima dengan rincian sebagai berikut:

{$allocBlock}
Pembayaran diterima pada:
{$receivedAt}

Tagihan untuk entri ini telah lunas.

{$tail}
TXT;
        }

        $lineLeftovers = self::breakdownRemainingPerLine($breakdown, (float) $invoice->totalPaid());
        $remainLines = [];
        $n = 1;
        foreach ($lineLeftovers as $row) {
            if ($row['remaining'] <= 0.009) {
                continue;
            }
            $remainLines[] = $n.'. '.$row['label'].' — '.self::rp($row['remaining']);
            $n++;
        }
        $remainBlock = $remainLines !== []
            ? "Sisa tagihan yang belum dibayarkan: {$remainingFmt}\ndengan rincian:\n".implode("\n", $remainLines)
            : "Sisa tagihan yang belum dibayarkan: {$remainingFmt}";

        return <<<TXT
Assalamu'alaikum Warahmatullahi Wabarakatuh.

Alhamdulillah, pembayaran sebesar {$amountFmt} atas nama ananda *{$student}* telah kami terima dengan rincian sebagai berikut:

{$allocBlock}
Pembayaran diterima pada:
{$receivedAt}

{$remainBlock}

{$tail}
TXT;
    }

    /**
     * @param  array<int, array{label:string, amount:float}>  $breakdown
     * @return array<int, array{label:string, amount:float, partial:bool}>
     */
    private static function fifoAllocateToBreakdown(array $breakdown, float $paidBefore, float $paymentAmount): array
    {
        $cum = 0.0;
        $x = $paidBefore;
        $xEnd = $paidBefore + $paymentAmount;
        $out = [];

        foreach ($breakdown as $row) {
            $lineTotal = (float) ($row['amount'] ?? 0);
            $label = trim((string) ($row['label'] ?? 'Item'));
            $lineStart = $cum;
            $lineEnd = $cum + $lineTotal;
            $overlapStart = max($lineStart, $x);
            $overlapEnd = min($lineEnd, $xEnd);
            $portion = max(0.0, $overlapEnd - $overlapStart);
            if ($portion > 0.009) {
                $out[] = [
                    'label' => $label,
                    'amount' => $portion,
                    'partial' => $portion + 0.009 < $lineTotal,
                ];
            }
            $cum = $lineEnd;
        }

        return $out;
    }

    /**
     * @param  array<int, array{label:string, amount:float}>  $breakdown
     * @return array<int, array{label:string, remaining:float}>
     */
    private static function breakdownRemainingPerLine(array $breakdown, float $totalVerifiedPaid): array
    {
        $assignLeft = $totalVerifiedPaid;
        $rows = [];

        foreach ($breakdown as $row) {
            $lineTotal = (float) ($row['amount'] ?? 0);
            $label = trim((string) ($row['label'] ?? 'Item'));
            $used = min($lineTotal, max(0.0, $assignLeft));
            $assignLeft -= $used;
            $rows[] = ['label' => $label, 'remaining' => max(0.0, $lineTotal - $used)];
        }

        return $rows;
    }

    private static function invoiceFallbackLineTitle(Invoice $invoice): string
    {
        $type = (string) ($invoice->paymentType?->name ?? 'Tagihan');
        $month = $invoice->month;
        if (is_int($month) || (is_numeric($month) && (int) $month >= 1 && (int) $month <= 12)) {
            $m = (int) $month;
            $year = $invoice->due_date instanceof CarbonInterface
                ? (int) $invoice->due_date->format('Y')
                : (int) now()->format('Y');
            $monthName = self::MONTH_NAMES[$m] ?? 'Bulan '.$m;

            return $monthName.' '.$year.' ('.$type.')';
        }

        return $type;
    }

    private static function formatIdDate(CarbonInterface|\DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '-';
        }
        $c = $date instanceof CarbonInterface ? $date : \Illuminate\Support\Carbon::parse($date);
        $day = (int) $c->format('j');
        $month = (int) $c->format('n');
        $year = (int) $c->format('Y');

        return $day.' '.(self::MONTH_NAMES[$month] ?? $c->format('F')).' '.$year;
    }

    private static function rp(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
