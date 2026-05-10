<?php

namespace App\Exports;

use App\Models\TingkatSekolah;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class FormalTingkatReferensiSheet implements FromCollection, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Referensi_Tingkat';
    }

    public function headings(): array
    {
        return [
            'tingkat_sekolah_id',
            'tingkat_code',
            'name',
            'group',
            'order',
            'is_billable',
        ];
    }

    public function collection()
    {
        return TingkatSekolah::query()
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (TingkatSekolah $t): array => [
                'tingkat_sekolah_id' => $t->id,
                'tingkat_code' => (string) $t->code,
                'name' => (string) $t->name,
                'group' => (string) ($t->group ?? ''),
                'order' => (int) $t->order,
                'is_billable' => $t->is_billable ? 'ya' : 'tidak',
            ]);
    }
}
