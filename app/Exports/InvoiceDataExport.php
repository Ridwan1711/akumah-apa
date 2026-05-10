<?php

namespace App\Exports;

use App\Models\Invoice;
use App\Services\Authorization\InvoiceVisibilityScope;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoiceDataExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected Request $request,
        protected InvoiceVisibilityScope $invoiceVisibilityScope,
    ) {}

    public function query()
    {
        $user = $this->request->user();
        abort_unless($user !== null, 403);

        $query = Invoice::query()
            ->when($this->request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($this->request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->when($this->request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($this->request->month, fn ($q, $m) => $q->where('month', $m))
            ->when($this->request->search, fn ($q, $s) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->when(
                $this->request->filled('tingkat_sekolah_id'),
                fn ($q) => $q->forFormalTingkat((int) $this->request->integer('tingkat_sekolah_id'))
            )
            ->when(
                ! $this->request->filled('tingkat_sekolah_id') && $this->request->filled('class_id'),
                fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('current_class_id', (int) $this->request->integer('class_id')))
            )
            ->when(
                $this->request->filled('division_code'),
                fn ($q) => $q->whereHas(
                    'student.activePositions',
                    fn ($positionQuery) => $positionQuery->where('division_code', (string) $this->request->string('division_code'))
                )
            );

        $this->invoiceVisibilityScope->applyToInvoiceQuery($query, $user);

        return $query
            ->with([
                'student:id,nis,full_name',
                'paymentType:id,name,code',
                'academicYear:id,name',
                'tingkatSekolah:id,name,code',
            ])
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', \App\Models\Payment::STATUS_VERIFIED),
            ], 'amount')
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'invoice_number',
            'nis',
            'student_name',
            'payment_type_code',
            'payment_type_name',
            'academic_year_name',
            'tingkat_formal_name',
            'month',
            'amount',
            'discount_amount',
            'final_amount',
            'verified_paid',
            'remaining',
            'status',
            'due_date',
            'notes',
        ];
    }

    /**
     * @param  Invoice  $invoice
     */
    public function map($invoice): array
    {
        $paid = (float) ($invoice->verified_paid_amount ?? 0);
        $remaining = max(0, (float) $invoice->final_amount - $paid);

        return [
            $invoice->invoice_number,
            $invoice->student?->nis ?? '',
            $invoice->student?->full_name ?? '',
            $invoice->paymentType?->code ?? '',
            $invoice->paymentType?->name ?? '',
            $invoice->academicYear?->name ?? '',
            (string) ($invoice->tingkatSekolah?->name ?? ''),
            $invoice->month !== null ? (string) $invoice->month : '',
            (float) $invoice->amount,
            (float) $invoice->discount_amount,
            (float) $invoice->final_amount,
            $paid,
            $remaining,
            (string) $invoice->status,
            $invoice->due_date?->toDateString() ?? '',
            (string) ($invoice->notes ?? ''),
        ];
    }
}
