<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class InvoiceTemplatePetunjukSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk';
    }

    public function array(): array
    {
        return [
            ['Kolom', 'Penjelasan'],
            ['nis', 'NIS santri aktif (wajib).'],
            ['payment_type_code', 'Kode jenis bayar dari master (wajib, contoh: SPP).'],
            ['academic_year_name', 'Nama tahun ajaran persis di sistem (wajib jika academic_year_id kosong).'],
            ['academic_year_id', 'Alternatif: ID tahun ajaran numerik.'],
            ['month', '1–12 untuk tagihan bulanan; kosongkan jika non-bulanan.'],
            ['amount', 'Nominal dasar sebelum diskon (wajib).'],
            ['discount_amount', 'Diskon manual tambahan (opsional, default 0). Diskon dari master santri tetap dihitung otomatis.'],
            ['due_date', 'Tanggal jatuh tempo YYYY-MM-DD (wajib).'],
            ['notes', 'Catatan opsional.'],
            ['breakdown_json', 'JSON array [{"label":"...","amount":...}] opsional. Jika kosong, rincian diisi dari jenis bayar.'],
        ];
    }
}
