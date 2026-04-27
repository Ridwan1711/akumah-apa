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
        $totalInvoiced = Invoice::whereNotIn('status', [Invoice::STATUS_CANCELLED])->sum('final_amount');
        $totalPaid = Payment::where('status', Payment::STATUS_VERIFIED)->sum('amount');
        $totalPending = Invoice::whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])->sum('final_amount');
        $totalOverdue = Invoice::where('status', Invoice::STATUS_OVERDUE)->sum('final_amount');

        $byCategory = PaymentType::select('payment_types.category')
            ->selectRaw('COALESCE(SUM(invoices.final_amount), 0) as total_invoiced')
            ->leftJoin('invoices', function ($join) {
                $join->on('payment_types.id', '=', 'invoices.payment_type_id')
                    ->whereNotIn('invoices.status', [Invoice::STATUS_CANCELLED]);
            })
            ->groupBy('payment_types.category')
            ->get();

        $byClass = SchoolClass::select('classes.id', 'classes.name')
            ->selectRaw('COUNT(DISTINCT invoices.id) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.final_amount), 0) as total_invoiced')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.status = ? THEN invoices.final_amount ELSE 0 END), 0) as total_paid', [Invoice::STATUS_PAID])
            ->leftJoin('students', 'classes.id', '=', 'students.current_class_id')
            ->leftJoin('invoices', function ($join) {
                $join->on('students.id', '=', 'invoices.student_id')
                    ->whereNotIn('invoices.status', [Invoice::STATUS_CANCELLED]);
            })
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('classes.name')
            ->get();

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
        ])
            ->whereIn('status', [Invoice::STATUS_OVERDUE, Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])
            ->when($request->class_id, fn ($q, $id) => $q->whereHas('student', fn ($sq) => $sq->where('current_class_id', $id)))
            ->when($request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->orderBy('due_date');

        return Inertia::render('admin/payment-reports/arrears', [
            'invoices' => $query->paginate(20)->withQueryString(),
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
