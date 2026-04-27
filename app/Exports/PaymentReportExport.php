<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentReportExport implements FromQuery, WithHeadings, WithMapping
{
    protected string $status;

    public function __construct(?string $status = null)
    {
        $this->status = $status ?? '';
    }

    public function query()
    {
        return Invoice::with([
            'student:id,nis,full_name,current_class_id',
            'student.currentClass:id,name',
            'paymentType:id,name,code',
        ])
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('student_id')
            ->orderBy('created_at');
    }

    public function headings(): array
    {
        return [
            'No. Invoice',
            'NIS',
            'Nama Santri',
            'Kelas',
            'Jenis Bayar',
            'Bulan',
            'Total Tagihan',
            'Diskon',
            'Tagihan Akhir',
            'Status',
            'Jatuh Tempo',
        ];
    }

    public function map($inv): array
    {
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return [
            $inv->invoice_number,
            $inv->student?->nis ?? '-',
            $inv->student?->full_name ?? '-',
            $inv->student?->currentClass?->name ?? '-',
            $inv->paymentType?->name ?? '-',
            $inv->month ? ($monthNames[$inv->month] ?? $inv->month) : '-',
            (float) $inv->amount,
            (float) $inv->discount_amount,
            (float) $inv->final_amount,
            $inv->status ?? '-',
            $inv->due_date?->format('Y-m-d') ?? '-',
        ];
    }
}
