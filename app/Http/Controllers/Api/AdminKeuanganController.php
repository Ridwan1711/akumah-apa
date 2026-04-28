<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkRun;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\PaymentVerifiedNotification;
use App\Services\Authorization\InvoiceVisibilityScope;
use App\Support\Authorization\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminKeuanganController extends Controller
{
    public function __construct(
        private readonly InvoiceVisibilityScope $invoiceVisibilityScope,
    ) {}

    public function indexInvoices(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::INVOICE_VIEW), 403, 'Anda tidak memiliki izin untuk melihat tagihan.');

        $query = Invoice::with([
            'student:id,nis,full_name,current_class_id',
            'student.currentClass:id,name',
            'paymentType:id,name,code,category',
            'academicYear:id,name',
        ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->month, fn ($q, $m) => $q->where('month', $m))
            ->when($request->search, fn ($q, $s) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->when($request->class_id, fn ($q, $id) => $q->whereHas('student', fn ($sq) => $sq->where('current_class_id', $id)))
            ->orderByDesc('created_at');
        $query = $this->invoiceVisibilityScope->applyToInvoiceQuery($query, $request->user());

        $invoices = $query->paginate($request->input('per_page', 20))->withQueryString();

        return response()->json([
            'invoices' => $invoices,
            'payment_types' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'academic_years' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'payment_type_id', 'academic_year_id', 'month', 'search', 'class_id']),
            'status_counts' => [
                'all' => (clone $query)->count(),
                'pending' => (clone $query)->where('status', Invoice::STATUS_PENDING)->count(),
                'partial' => (clone $query)->where('status', Invoice::STATUS_PARTIAL)->count(),
                'paid' => (clone $query)->where('status', Invoice::STATUS_PAID)->count(),
                'overdue' => (clone $query)->where('status', Invoice::STATUS_OVERDUE)->count(),
            ],
        ]);
    }

    public function generateMeta(): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission(Permissions::INVOICE_VIEW), 403, 'Anda tidak memiliki izin untuk melihat data tagihan.');

        return response()->json([
            'payment_types' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'is_recurring']),
            'academic_years' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'grade_level_id']),
        ]);
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::INVOICE_CREATE), 403, 'Anda tidak memiliki izin membuat tagihan.');

        $validated = $request->validate([
            'payment_type_id' => ['required', 'exists:payment_types,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['exists:diniyah_classes,id'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'due_date' => ['required', 'date'],
            'send_notification_for_existing' => ['sometimes', 'boolean'],
        ]);
        $validated['send_notification_for_existing'] = (bool) ($validated['send_notification_for_existing'] ?? true);

        $run = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => ImportRun::JOB_INVOICE_BULK_GENERATE,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => $request->user()?->id,
            'file_name' => 'bulk-generate-invoice-api',
            'file_path' => '-',
            'meta' => $validated,
        ]);

        ProcessBulkRun::dispatch($run->id);

        return response()->json([
            'message' => 'Bulk generate tagihan diproses di background.',
            'run_id' => $run->id,
            'run_uuid' => $run->uuid,
            'status' => $run->status,
        ]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::INVOICE_CREATE), 403, 'Anda tidak memiliki izin membuat tagihan.');

        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_type_id' => ['required', 'exists:payment_types,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $discount = $this->resolveDiscount($validated['student_id'], $validated['payment_type_id'], $validated['academic_year_id'], $validated['amount']);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateNumber(),
            'student_id' => $validated['student_id'],
            'payment_type_id' => $validated['payment_type_id'],
            'academic_year_id' => $validated['academic_year_id'],
            'month' => $validated['month'] ?? null,
            'amount' => $validated['amount'],
            'discount_amount' => $discount,
            'final_amount' => $validated['amount'] - $discount,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'] ?? null,
            'generated_by' => $request->user()->id,
        ]);

        $invoice->load(['student:id,nis,full_name', 'paymentType:id,name,code', 'academicYear:id,name']);
        $this->notifyInvoiceCreatedTargets($invoice);

        return response()->json(['message' => 'Tagihan berhasil dibuat.', 'invoice' => $invoice], 201);
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        $user = request()->user();
        abort_unless($user?->hasPermission(Permissions::INVOICE_VIEW), 403, 'Anda tidak memiliki izin untuk melihat tagihan.');

        $visibleQuery = Invoice::query()->whereKey($invoice->id);
        $visibleQuery = $this->invoiceVisibilityScope->applyToInvoiceQuery($visibleQuery, $user);
        abort_unless($visibleQuery->exists(), 403, 'Tagihan ini tidak termasuk dalam cakupan akses Anda.');

        $invoice->load([
            'student:id,nis,full_name,current_class_id',
            'student.currentClass:id,name',
            'paymentType:id,name,code,category',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
            'payments.verifier:id,name',
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->remaining = $invoice->remainingAmount();

        return response()->json(['invoice' => $invoice]);
    }

    public function cancelInvoice(Invoice $invoice): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission(Permissions::INVOICE_CANCEL), 403, 'Anda tidak memiliki izin membatalkan tagihan.');

        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['message' => 'Tagihan yang sudah lunas tidak bisa dibatalkan.'], 422);
        }

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        return response()->json(['message' => 'Tagihan berhasil dibatalkan.', 'invoice' => $invoice->fresh()]);
    }

    public function indexPayments(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::PAYMENT_VIEW), 403, 'Anda tidak memiliki izin untuk melihat pembayaran.');

        $query = Payment::with([
            'invoice:id,invoice_number,student_id,payment_type_id,final_amount',
            'invoice.student:id,nis,full_name',
            'invoice.paymentType:id,name,code',
            'verifier:id,name',
        ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->search, fn ($q, $s) => $q->whereHas('invoice.student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->orderByDesc('created_at');
        $query->whereHas('invoice', fn (Builder $invoiceQuery) => $this->invoiceVisibilityScope->applyToInvoiceQuery($invoiceQuery, $request->user()));

        $payments = $query->paginate($request->input('per_page', 20))->withQueryString();

        return response()->json([
            'payments' => $payments,
            'filters' => $request->only(['status', 'payment_method', 'search']),
            'pending_count' => Payment::where('status', Payment::STATUS_PENDING)->count(),
        ]);
    }

    public function verifyPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::PAYMENT_VERIFY), 403, 'Anda tidak memiliki izin memverifikasi pembayaran.');

        if ($payment->status !== Payment::STATUS_PENDING) {
            return response()->json(['message' => 'Pembayaran ini sudah diverifikasi atau ditolak.'], 422);
        }

        $payment->update([
            'status' => Payment::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $payment->invoice->recalculateStatus();

        $student = $payment->invoice->student;
        if ($student) {
            $student->guardians()->whereHas('user')->with('user')->each(function ($guardian) use ($payment) {
                $guardian->user?->notify(new PaymentVerifiedNotification($payment));
            });
        }

        $payment->load(['invoice', 'verifier:id,name']);

        return response()->json(['message' => 'Pembayaran berhasil diverifikasi.', 'payment' => $payment]);
    }

    public function rejectPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::PAYMENT_REJECT), 403, 'Anda tidak memiliki izin menolak pembayaran.');

        if ($payment->status !== Payment::STATUS_PENDING) {
            return response()->json(['message' => 'Pembayaran ini sudah diverifikasi atau ditolak.'], 422);
        }

        $request->validate(['reason' => ['nullable', 'string'], 'notes' => ['nullable', 'string']]);
        $notes = $request->input('reason') ?? $request->input('notes') ?? $payment->notes;

        $payment->update([
            'status' => Payment::STATUS_REJECTED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $notes,
        ]);

        $payment->invoice->recalculateStatus();
        $payment->load(['invoice', 'verifier:id,name']);

        return response()->json(['message' => 'Pembayaran berhasil ditolak.', 'payment' => $payment]);
    }

    public function reportSummary(): JsonResponse
    {
        abort_unless(request()->user()?->hasPermission(Permissions::PAYMENT_REPORT_VIEW), 403, 'Anda tidak memiliki izin untuk melihat laporan pembayaran.');

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

        return response()->json([
            'stats' => [
                'total_invoiced' => (float) $totalInvoiced,
                'total_paid' => (float) $totalPaid,
                'total_pending' => (float) $totalPending,
                'total_overdue' => (float) $totalOverdue,
                'collection_rate' => $totalInvoiced > 0 ? round((float) ($totalPaid / $totalInvoiced) * 100, 1) : 0,
            ],
            'by_category' => $byCategory,
            'by_class' => $byClass,
            'recent_payments' => $recentPayments,
        ]);
    }

    public function reportArrears(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::PAYMENT_REPORT_VIEW), 403, 'Anda tidak memiliki izin untuk melihat laporan tunggakan.');

        $query = Invoice::query();
        $query->with([
            'student:id,nis,full_name,current_class_id',
            'student.currentClass:id,name',
            'paymentType:id,name,code',
        ]);
        $query->whereIn('status', [Invoice::STATUS_OVERDUE, Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL]);
        if ($request->class_id) {
            $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('current_class_id', $request->class_id));
        }
        if ($request->payment_type_id) {
            $query->where('payment_type_id', $request->payment_type_id);
        }
        $query->orderBy('due_date');
        $query = $this->invoiceVisibilityScope->applyToInvoiceQuery($query, $request->user());

        $invoices = $query->paginate($request->input('per_page', 20))->withQueryString();

        return response()->json([
            'invoices' => $invoices,
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name']),
            'payment_types' => PaymentType::where('is_active', true)->get(['id', 'name']),
            'filters' => $request->only(['class_id', 'payment_type_id']),
        ]);
    }

    private function resolveAmount(PaymentType $type, Student $student): ?float
    {
        if ($student->is_kuliah) {
            return $type->kuliah_amount !== null ? (float) $type->kuliah_amount : null;
        }

        return (float) $type->default_amount;
    }

    private function resolveDiscount(int $studentId, int $paymentTypeId, int $academicYearId, float $amount): float
    {
        $discount = StudentDiscount::where('student_id', $studentId)
            ->where('payment_type_id', $paymentTypeId)
            ->where('academic_year_id', $academicYearId)
            ->first();

        return $discount ? $discount->calculateDiscount($amount) : 0;
    }

    private function notifyInvoiceCreatedTargets(Invoice $invoice): void
    {
        $invoice->loadMissing('student.user', 'student.guardians.user');
        $student = $invoice->student;
        if (! $student) {
            return;
        }

        // Primary target: wali
        $student->guardians
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn ($user) => $user->notify(new InvoiceCreatedNotification($invoice)));

        // Optional target: santri (if account exists)
        if ($student->user) {
            $student->user->notify(new InvoiceCreatedNotification($invoice));
        }
    }
}
