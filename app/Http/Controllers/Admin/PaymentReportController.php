<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PaymentReportExport;
use App\Http\Controllers\Controller;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PaymentReportController extends Controller
{
    public function summary(Request $request): Response
    {
        $remainingByInvoice = Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID])
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->get(['id', 'final_amount', 'status']);
        $sumRemaining = fn ($statuses) => (float) $remainingByInvoice
            ->whereIn('status', (array) $statuses)
            ->sum(fn (Invoice $invoice): float => max(0, (float) $invoice->final_amount - (float) ($invoice->verified_paid_amount ?? 0)));

        $totalInvoiced = Invoice::whereNotIn('status', [Invoice::STATUS_CANCELLED])->sum('final_amount');
        $totalPaid = Payment::where('status', Payment::STATUS_VERIFIED)->sum('amount');
        $totalPending = $sumRemaining([Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL]);
        $totalOverdue = $sumRemaining([Invoice::STATUS_OVERDUE]);

        $byCategory = PaymentType::select('payment_types.category')
            ->selectRaw('COALESCE(SUM(invoices.final_amount), 0) as total_invoiced')
            ->leftJoin('invoices', function ($join) {
                $join->on('payment_types.id', '=', 'invoices.payment_type_id')
                    ->whereNotIn('invoices.status', [Invoice::STATUS_CANCELLED]);
            })
            ->groupBy('payment_types.category')
            ->get();

        $byClass = SchoolClass::query()
            ->select('classes.id', 'classes.name')
            ->orderBy('classes.name')
            ->get()
            ->map(function (SchoolClass $class): array {
                $invoiceQuery = Invoice::query()
                    ->whereHas('student', fn ($studentQuery) => $studentQuery->where('current_class_id', $class->id))
                    ->whereNotIn('status', [Invoice::STATUS_CANCELLED]);

                return [
                    'id' => (int) $class->id,
                    'name' => (string) $class->name,
                    'invoice_count' => (int) (clone $invoiceQuery)->count(),
                    'total_invoiced' => (float) (clone $invoiceQuery)->sum('final_amount'),
                    'total_paid' => (float) Payment::query()
                        ->where('status', Payment::STATUS_VERIFIED)
                        ->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->whereHas('student', fn ($studentQuery) => $studentQuery->where('current_class_id', $class->id)))
                        ->sum('amount'),
                ];
            });

        $recentPayments = Payment::with([
            'invoice:id,invoice_number,student_id',
            'invoice.student:id,full_name',
        ])
            ->where('status', Payment::STATUS_VERIFIED)
            ->orderByDesc('verified_at')
            ->limit(10)
            ->get();

        return Inertia::render('admin/payment-reports/summary', [
            'stats' => [
                'total_invoiced' => (float) $totalInvoiced,
                'total_paid' => (float) $totalPaid,
                'total_pending' => (float) $totalPending,
                'total_overdue' => (float) $totalOverdue,
                'collection_rate' => $totalInvoiced > 0 ? round(($totalPaid / $totalInvoiced) * 100, 1) : 0,
            ],
            'byCategory' => $byCategory,
            'byClass' => $byClass,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function arrears(Request $request): Response
    {
        $query = Invoice::with([
            'student:id,nis,full_name,current_class_id',
            'student.currentClass:id,name',
            'paymentType:id,name,code',
        ])->withSum([
            'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
        ], 'amount')
            ->whereIn('status', [Invoice::STATUS_OVERDUE, Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])
            ->when($request->class_id, fn ($q, $id) => $q->whereHas('student', fn ($sq) => $sq->where('current_class_id', $id)))
            ->when($request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->orderBy('due_date');

        $invoices = $query->paginate(20)->withQueryString();
        $invoices->getCollection()->transform(function (Invoice $invoice): Invoice {
            $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
            $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);

            return $invoice;
        });

        return Inertia::render('admin/payment-reports/arrears', [
            'invoices' => $invoices,
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name']),
            'paymentTypes' => PaymentType::where('is_active', true)->get(['id', 'name']),
            'filters' => $request->only(['class_id', 'payment_type_id']),
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'laporan-keuangan-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new PaymentReportExport($request->status),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
