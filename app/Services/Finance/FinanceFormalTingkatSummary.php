<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TingkatSekolah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FinanceFormalTingkatSummary
{
    /**
     * Rekap tagihan dan pembayaran terverifikasi per tingkat sekolah formal (plus bucket lainnya).
     *
     * @return Collection<int, object{
     *   id: ?int,
     *   name: string,
     *   code: ?string,
     *   invoice_count: int,
     *   total_invoiced: float,
     *   total_paid: float,
     * }>
     */
    public static function invoicedPaidByTingkat(Builder $invoiceBaseQuery): Collection
    {
        $base = (clone $invoiceBaseQuery)->whereNotIn('status', [Invoice::STATUS_CANCELLED]);
        $tingkats = TingkatSekolah::query()->orderBy('order')->orderBy('name')->get();
        $matchedIds = collect();
        $rows = collect();

        foreach ($tingkats as $tingkat) {
            $invoiceQuery = (clone $base)->forFormalTingkat((int) $tingkat->id);
            $ids = (clone $invoiceQuery)->pluck('invoices.id');
            $matchedIds = $matchedIds->merge($ids);
            $invoiceCount = (clone $invoiceQuery)->count();
            $totalInvoiced = (float) (clone $invoiceQuery)->sum('final_amount');
            $idList = $ids->map(fn ($id): int => (int) $id)->values()->all();
            $totalPaid = $idList === []
                ? 0.0
                : (float) Payment::query()
                    ->where('status', Payment::STATUS_VERIFIED)
                    ->whereIn('invoice_id', $idList)
                    ->sum('amount');

            $rows->push((object) [
                'id' => (int) $tingkat->id,
                'name' => (string) $tingkat->name,
                'code' => $tingkat->code,
                'invoice_count' => $invoiceCount,
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
            ]);
        }

        $uniqueMatched = $matchedIds->unique()->values()->all();
        $otherQuery = (clone $base)->when(
            $uniqueMatched !== [],
            fn (Builder $q) => $q->whereNotIn('invoices.id', $uniqueMatched)
        );
        $otherIds = (clone $otherQuery)->pluck('invoices.id')->map(fn ($id): int => (int) $id)->values()->all();
        $rows->push((object) [
            'id' => null,
            'name' => 'Tanpa tingkat formal / lainnya',
            'code' => null,
            'invoice_count' => (clone $otherQuery)->count(),
            'total_invoiced' => (float) (clone $otherQuery)->sum('final_amount'),
            'total_paid' => $otherIds === []
                ? 0.0
                : (float) Payment::query()
                    ->where('status', Payment::STATUS_VERIFIED)
                    ->whereIn('invoice_id', $otherIds)
                    ->sum('amount'),
        ]);

        return $rows;
    }
}
