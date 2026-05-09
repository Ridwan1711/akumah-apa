<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchInvoiceRemindersJob;
use App\Jobs\ProcessBulkRun;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\PaymentVerifiedNotification;
use App\Services\Authorization\InvoiceVisibilityScope;
use App\Services\Finance\DispatchInvoiceRemindersAction;
use App\Services\Finance\FinanceWhatsappOutbound;
use App\Services\Finance\FinanceWhatsappPhone;
use App\Services\Finance\FinanceWhatsappRecipient;
use App\Services\Finance\InstallmentService;
use App\Support\Authorization\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminKeuanganController extends Controller
{
    public function __construct(
        private readonly InvoiceVisibilityScope $invoiceVisibilityScope,
        private readonly DispatchInvoiceRemindersAction $dispatchInvoiceRemindersAction,
        private readonly FinanceWhatsappOutbound $financeWhatsappOutbound,
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
            ->withCount('payments')
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->withSum([
                'payments as pending_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_PENDING),
            ], 'amount')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->month, fn ($q, $m) => $q->where('month', $m))
            ->when($request->search, fn ($q, $s) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->when($request->class_id, fn ($q, $id) => $q->whereHas('student', fn ($sq) => $sq->where('current_class_id', $id)))
            ->orderByDesc('created_at');
        $query = $this->invoiceVisibilityScope->applyToInvoiceQuery($query, $request->user());

        $invoices = $query->paginate($request->input('per_page', 20))->withQueryString();
        $invoices->getCollection()->transform(function (Invoice $invoice): Invoice {
            $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
            $invoice->pending_amount = (float) ($invoice->pending_paid_amount ?? 0);
            $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);

            return $invoice;
        });

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
            'payment_types' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'is_recurring', 'default_breakdown']),
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
            'breakdown' => ['nullable', 'array'],
            'breakdown.*.label' => ['required_with:breakdown', 'string', 'max:120'],
            'breakdown.*.amount' => ['required_with:breakdown', 'numeric', 'min:0.01'],
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
            'breakdown' => ['nullable', 'array'],
            'breakdown.*.label' => ['required_with:breakdown', 'string', 'max:120'],
            'breakdown.*.amount' => ['required_with:breakdown', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $paymentType = PaymentType::query()->findOrFail((int) $validated['payment_type_id']);
        $breakdown = $this->resolveInvoiceBreakdown(
            $paymentType,
            (float) $validated['amount'],
            $validated['breakdown'] ?? null
        );

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
            'breakdown' => $breakdown,
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
            'paymentType:id,name,code,category,default_breakdown',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
            'payments.verifier:id,name',
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->pending_amount = $invoice->pendingAmount();
        $invoice->remaining = $invoice->remainingAmount();
        $invoice->breakdown_items = $invoice->resolvedBreakdown();

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

    public function updateInvoiceBreakdown(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::INVOICE_CREATE), 403, 'Anda tidak memiliki izin mengubah rincian tagihan.');

        $visibleQuery = Invoice::query()->whereKey($invoice->id);
        $visibleQuery = $this->invoiceVisibilityScope->applyToInvoiceQuery($visibleQuery, $request->user());
        abort_unless($visibleQuery->exists(), 403, 'Tagihan ini tidak termasuk dalam cakupan akses Anda.');

        $validated = $request->validate([
            'breakdown' => ['nullable', 'array'],
            'breakdown.*.label' => ['required_with:breakdown', 'string', 'max:120'],
            'breakdown.*.amount' => ['required_with:breakdown', 'numeric', 'min:0.01'],
        ]);

        $breakdown = PaymentType::normalizeBreakdownItems($validated['breakdown'] ?? null);
        if ($breakdown !== []) {
            $sum = PaymentType::breakdownTotal($breakdown);
            if (abs($sum - round((float) $invoice->amount, 2)) > 0.01) {
                throw ValidationException::withMessages([
                    'breakdown' => 'Total rincian harus sama dengan nominal tagihan.',
                ]);
            }
        }

        $invoice->update([
            'breakdown' => $breakdown === [] ? null : $breakdown,
        ]);
        $invoice->load([
            'paymentType:id,name,code,default_breakdown',
        ]);
        $invoice->breakdown_items = $invoice->resolvedBreakdown();

        return response()->json([
            'message' => 'Rincian tagihan berhasil diperbarui.',
            'invoice' => $invoice,
        ]);
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
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('payment_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('payment_date', '<=', $d))
            ->orderByDesc('created_at');
        $query->whereHas('invoice', fn (Builder $invoiceQuery) => $this->invoiceVisibilityScope->applyToInvoiceQuery($invoiceQuery, $request->user()));

        $payments = $query->paginate($request->input('per_page', 20))->withQueryString();

        return response()->json([
            'payments' => $payments,
            'filters' => $request->only(['status', 'payment_method', 'search']),
            'pending_count' => Payment::where('status', Payment::STATUS_PENDING)->count(),
        ]);
    }

    public function storePayment(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::PAYMENT_VERIFY), 403, 'Anda tidak memiliki izin mencatat pembayaran.');

        $visibleQuery = Invoice::query()->whereKey($invoice->id);
        $visibleQuery = $this->invoiceVisibilityScope->applyToInvoiceQuery($visibleQuery, $request->user());
        abort_unless($visibleQuery->exists(), 403, 'Tagihan ini tidak termasuk dalam cakupan akses Anda.');

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])) {
            return response()->json(['message' => 'Tagihan ini sudah lunas atau dibatalkan.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:'.Payment::METHOD_CASH.','.Payment::METHOD_BANK_TRANSFER],
            'payment_date' => ['required', 'date'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'notes' => ['nullable', 'string'],
        ]);
        app(InstallmentService::class)->validateAmount($invoice, (float) $validated['amount']);

        $proofPath = $request->hasFile('proof_file')
            ? $request->file('proof_file')->store('payment-proofs', 'public')
            : null;

        $payment = Payment::create([
            'payment_number' => Payment::generateNumber(),
            'invoice_id' => $invoice->id,
            'amount' => (float) $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'proof_file' => $proofPath,
            'status' => Payment::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $invoice->recalculateStatus();
        $payment->load(['invoice', 'verifier:id,name']);

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'payment' => $payment,
        ], 201);
    }

    public function verifyPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()?->hasPermission(Permissions::PAYMENT_VERIFY), 403, 'Anda tidak memiliki izin memverifikasi pembayaran.');
        $validated = $request->validate([
            'verified_amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        if ($payment->status !== Payment::STATUS_PENDING) {
            return response()->json(['message' => 'Pembayaran ini sudah diverifikasi atau ditolak.'], 422);
        }

        $verifiedAmount = isset($validated['verified_amount'])
            ? (float) $validated['verified_amount']
            : (float) $payment->amount;
        app(InstallmentService::class)->validateAmount($payment->invoice, $verifiedAmount, true);

        $payment->update([
            'amount' => $verifiedAmount,
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

        $this->financeWhatsappOutbound->queuePaymentVerifiedForPayment($payment);

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
        $user = request()->user();
        abort_unless($user?->hasPermission(Permissions::PAYMENT_REPORT_VIEW), 403, 'Anda tidak memiliki izin untuk melihat laporan pembayaran.');

        $visibleInvoiceQuery = Invoice::query();
        $visibleInvoiceQuery = $this->invoiceVisibilityScope->applyToInvoiceQuery($visibleInvoiceQuery, $user);
        $visibleInvoiceIds = (clone $visibleInvoiceQuery)->select('invoices.id');
        $visiblePaymentsQuery = Payment::query()->whereIn('invoice_id', $visibleInvoiceIds);
        $today = Carbon::today();
        $endOfWeekWindow = (clone $today)->addDays(7);

        $totalInvoiced = (clone $visibleInvoiceQuery)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
            ->sum('final_amount');
        $totalPaid = (clone $visiblePaymentsQuery)
            ->where('status', Payment::STATUS_VERIFIED)
            ->sum('amount');
        $totalPending = $this->sumRemainingAmount(
            (clone $visibleInvoiceQuery)->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])
        );
        $totalOverdue = $this->sumRemainingAmount(
            (clone $visibleInvoiceQuery)->where('status', Invoice::STATUS_OVERDUE)
        );
        $dueThisWeekAmount = $this->sumRemainingAmount(
            (clone $visibleInvoiceQuery)
                ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])
                ->whereBetween('due_date', [$today->toDateString(), $endOfWeekWindow->toDateString()])
        );
        $dueThisWeekCount = (clone $visibleInvoiceQuery)
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])
            ->whereBetween('due_date', [$today->toDateString(), $endOfWeekWindow->toDateString()])
            ->count();
        $paidCount = (clone $visibleInvoiceQuery)->where('status', Invoice::STATUS_PAID)->count();
        $pendingCount = (clone $visibleInvoiceQuery)->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL])->count();
        $overdueCount = (clone $visibleInvoiceQuery)->where('status', Invoice::STATUS_OVERDUE)->count();
        $unverifiedPaymentCount = (clone $visiblePaymentsQuery)
            ->where('status', Payment::STATUS_PENDING)
            ->count();

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
            ->whereIn('invoice_id', $visibleInvoiceIds)
            ->orderByDesc('verified_at')
            ->limit(10)
            ->get();

        $topArrears = (clone $visibleInvoiceQuery)
            ->with([
                'student:id,full_name,current_class_id',
                'student.currentClass:id,name',
                'paymentType:id,name',
            ])
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_PAID])
            ->get()
            ->map(function (Invoice $invoice): array {
                $paidAmount = (float) ($invoice->verified_paid_amount ?? 0);
                $remainingAmount = max(0, (float) $invoice->final_amount - $paidAmount);

                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'student_id' => (int) $invoice->student_id,
                    'student_name' => (string) ($invoice->student?->full_name ?? '-'),
                    'class_name' => (string) ($invoice->student?->currentClass?->name ?? '-'),
                    'payment_type_name' => (string) ($invoice->paymentType?->name ?? '-'),
                    'remaining_amount' => $remainingAmount,
                    'due_date' => $invoice->due_date?->toDateString(),
                ];
            })
            ->sortByDesc('remaining_amount')
            ->take(5)
            ->values();

        $paymentMethods = (clone $visiblePaymentsQuery)
            ->where('status', Payment::STATUS_VERIFIED)
            ->where('verified_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw("COALESCE(payment_method, 'unknown') as method")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->groupBy('payment_method')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) ($row->method ?? 'unknown'),
                'count' => (int) ($row->count ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ]);

        return response()->json([
            'stats' => [
                'total_invoiced' => (float) $totalInvoiced,
                'total_paid' => (float) $totalPaid,
                'total_pending' => (float) $totalPending,
                'total_overdue' => (float) $totalOverdue,
                'collection_rate' => $totalInvoiced > 0 ? round((float) ($totalPaid / $totalInvoiced) * 100, 1) : 0,
                'due_this_week_count' => (int) $dueThisWeekCount,
                'due_this_week_amount' => (float) $dueThisWeekAmount,
                'unverified_payment_count' => (int) $unverifiedPaymentCount,
                'paid_count' => (int) $paidCount,
                'pending_count' => (int) $pendingCount,
                'overdue_count' => (int) $overdueCount,
            ],
            'by_category' => $byCategory,
            'by_class' => $byClass,
            'recent_payments' => $recentPayments,
            'top_arrears' => $topArrears,
            'payment_methods' => $paymentMethods,
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

    public function sendReminderSingle(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission(Permissions::INVOICE_REMINDER_SEND), 403, 'Anda tidak memiliki izin mengirim reminder tagihan.');

        $visibleQuery = Invoice::query()->whereKey($invoice->id);
        $visibleQuery = $this->invoiceVisibilityScope->applyToInvoiceQuery($visibleQuery, $user);
        abort_unless($visibleQuery->exists(), 403, 'Tagihan ini tidak termasuk dalam cakupan akses Anda.');

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
            'send_app_notification' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
        ]);

        [$sendApp, $sendWhatsapp] = $this->validatedReminderChannels($validated);

        $invoice->loadMissing('student.guardians.user', 'paymentType');
        $result = $this->dispatchInvoiceRemindersAction->run(
            collect([$invoice]),
            $validated['message'] ?? null,
            $user?->id,
            $sendApp,
            $sendWhatsapp,
        );

        return response()->json([
            'message' => 'Reminder tagihan berhasil dikirim.',
            'invoice_id' => (int) $invoice->id,
            'sent_count' => $result['sent_count'],
            'recipients_without_account' => $result['recipients_without_account'],
            'wa_queued' => $result['wa_queued'],
            'last_reminder_sent_at' => $invoice->fresh()?->last_reminder_sent_at?->toIso8601String(),
        ]);
    }

    public function previewReminders(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Autentikasi diperlukan.');
        abort_unless($user?->hasPermission(Permissions::INVOICE_REMINDER_SEND), 403, 'Anda tidak memiliki izin preview reminder tagihan.');

        $validated = $request->validate([
            'invoice_ids' => ['nullable', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'all_unpaid' => ['nullable', 'boolean'],
            'payment_type_id' => ['nullable', 'integer', 'exists:payment_types,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
        ]);

        $invoices = $this->resolveReminderInvoices($validated, $user)->loadMissing(
            'student.currentClass',
            'student.guardians.user',
            'student.user:id,whatsapp_phone',
        );

        $preview = $this->buildReminderPreview($invoices);

        return response()->json($preview);
    }

    public function sendReminders(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Autentikasi diperlukan.');
        abort_unless($user?->hasPermission(Permissions::INVOICE_REMINDER_SEND), 403, 'Anda tidak memiliki izin mengirim reminder tagihan.');

        $validated = $request->validate([
            'invoice_ids' => ['nullable', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'all_unpaid' => ['nullable', 'boolean'],
            'payment_type_id' => ['nullable', 'integer', 'exists:payment_types,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'message' => ['nullable', 'string', 'max:500'],
            'send_app_notification' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
        ]);

        [$sendApp, $sendWhatsapp] = $this->validatedReminderChannels($validated);

        $invoices = $this->resolveReminderInvoices($validated, $user);
        $invoiceIds = $invoices->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $total = count($invoiceIds);

        if ($total === 0) {
            return response()->json([
                'message' => 'Tidak ada invoice valid dalam cakupan akses Anda.',
                'sent_count' => 0,
                'total' => 0,
            ], 422);
        }

        if ($total > 50) {
            DispatchInvoiceRemindersJob::dispatch(
                $invoiceIds,
                $validated['message'] ?? null,
                $user?->id,
                $sendApp,
                $sendWhatsapp,
            );

            return response()->json([
                'message' => 'Reminder diproses di background.',
                'queued' => true,
                'total' => $total,
                'send_app_notification' => $sendApp,
                'send_whatsapp' => $sendWhatsapp,
            ]);
        }

        $invoices->loadMissing('student.guardians.user', 'student.user:id,whatsapp_phone', 'paymentType');
        $result = $this->dispatchInvoiceRemindersAction->run(
            $invoices,
            $validated['message'] ?? null,
            $user?->id,
            $sendApp,
            $sendWhatsapp,
        );

        return response()->json([
            'message' => 'Reminder tagihan selesai diproses.',
            'queued' => false,
            'total' => $total,
            'sent_count' => $result['sent_count'],
            'recipients_without_account' => $result['recipients_without_account'],
            'wa_queued' => $result['wa_queued'],
            'failed' => $result['failed'],
        ]);
    }

    public function reportTrend(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasPermission(Permissions::PAYMENT_REPORT_VIEW), 403, 'Anda tidak memiliki izin untuk melihat tren pembayaran.');

        $months = (int) $request->integer('months', 12);
        $months = max(3, min($months, 24));
        $startMonth = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $visibleInvoiceQuery = Invoice::query();
        $visibleInvoiceQuery = $this->invoiceVisibilityScope->applyToInvoiceQuery($visibleInvoiceQuery, $user);
        $visibleInvoiceIds = (clone $visibleInvoiceQuery)->select('invoices.id');

        $invoicedByMonth = (clone $visibleInvoiceQuery)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED])
            ->whereDate('created_at', '>=', $startMonth->toDateString())
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month_key")
            ->selectRaw('COALESCE(SUM(final_amount), 0) as amount')
            ->groupByRaw("to_char(created_at, 'YYYY-MM')")
            ->pluck('amount', 'month_key');

        $overdueByMonth = (clone $visibleInvoiceQuery)
            ->where('status', Invoice::STATUS_OVERDUE)
            ->whereDate('created_at', '>=', $startMonth->toDateString())
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->get(['id', 'created_at', 'final_amount'])
            ->groupBy(fn (Invoice $invoice): string => Carbon::parse($invoice->created_at)->format('Y-m'))
            ->map(fn ($group): float => (float) $group->sum(fn (Invoice $invoice): float => max(0, (float) $invoice->final_amount - (float) ($invoice->verified_paid_amount ?? 0))));

        $paidByMonth = Payment::query()
            ->where('status', Payment::STATUS_VERIFIED)
            ->whereNotNull('verified_at')
            ->whereDate('verified_at', '>=', $startMonth->toDateString())
            ->whereIn('invoice_id', $visibleInvoiceIds)
            ->selectRaw("to_char(verified_at, 'YYYY-MM') as month_key")
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->groupByRaw("to_char(verified_at, 'YYYY-MM')")
            ->pluck('amount', 'month_key');

        $rows = collect(range(0, $months - 1))
            ->map(function (int $offset) use ($startMonth, $invoicedByMonth, $paidByMonth, $overdueByMonth): array {
                $monthDate = (clone $startMonth)->addMonths($offset);
                $monthKey = $monthDate->format('Y-m');

                return [
                    'key' => $monthKey,
                    'label' => $monthDate->translatedFormat('M y'),
                    'invoiced' => (float) ($invoicedByMonth[$monthKey] ?? 0),
                    'paid' => (float) ($paidByMonth[$monthKey] ?? 0),
                    'overdue' => (float) ($overdueByMonth[$monthKey] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'months' => $rows,
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

    /**
     * @param  array<string, mixed>  $validated
     * @return \Illuminate\Database\Eloquent\Collection<int, Invoice>
     */
    private function resolveReminderInvoices(array $validated, User $user)
    {
        /** @var Builder $query */
        $query = Invoice::query();
        $query->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE]);
        $query = $this->invoiceVisibilityScope->applyToInvoiceQuery($query, $user);

        if (! empty($validated['invoice_ids'])) {
            $query->whereIn('id', $validated['invoice_ids']);
        } elseif (($validated['all_unpaid'] ?? false) === true) {
            if (! empty($validated['payment_type_id'])) {
                $query->where('payment_type_id', $validated['payment_type_id']);
            }
            if (! empty($validated['academic_year_id'])) {
                $query->where('academic_year_id', $validated['academic_year_id']);
            }
            if (! empty($validated['class_id'])) {
                $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('current_class_id', $validated['class_id']));
            }
        } else {
            return collect();
        }

        return $query->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Invoice>  $invoices
     * @return array{
     *   invoices_count: int,
     *   unique_wali_count: int,
     *   recipients_with_account: int,
     *   recipients_without_account: int,
     *   sample_recipients: array<int, array{name:string,student:string}>,
     *   by_class: array<int, array{class_name:string,invoices_count:int}>
     * }
     */
    private function buildReminderPreview($invoices): array
    {
        $recipientsWithAccount = collect();
        $recipientsWithoutAccount = 0;
        $sampleRecipients = collect();
        $recipient = app(FinanceWhatsappRecipient::class);
        $waPhonesEstimate = collect();
        $waliWithoutAppWithPhone = 0;

        foreach ($invoices as $invoice) {
            $studentName = (string) ($invoice->student?->full_name ?? 'Santri');
            $student = $invoice->student;
            $guardians = $student?->guardians ?? collect();
            foreach ($guardians as $guardian) {
                if ($guardian->user) {
                    $recipientsWithAccount->push($guardian->user->id);
                    if ($sampleRecipients->count() < 5) {
                        $sampleRecipients->push([
                            'name' => (string) ($guardian->user->name ?? 'Wali'),
                            'student' => $studentName,
                        ]);
                    }
                    if ($student) {
                        $p = $recipient->resolve($student, $guardian->user);
                        if ($p !== null) {
                            $waPhonesEstimate->push($p);
                        }
                    }
                } else {
                    $recipientsWithoutAccount++;
                    if (! $guardian->without_phone && FinanceWhatsappPhone::normalize($guardian->phone) !== null) {
                        $waliWithoutAppWithPhone++;
                    }
                    if ($student) {
                        $p = $recipient->resolve($student, null, $guardian);
                        if ($p !== null) {
                            $waPhonesEstimate->push($p);
                        }
                    }
                }
            }
        }

        $byClass = $invoices
            ->groupBy(fn (Invoice $invoice): string => (string) ($invoice->student?->currentClass?->name ?? 'Tanpa Kelas'))
            ->map(fn ($group, $className): array => [
                'class_name' => (string) $className,
                'invoices_count' => $group->count(),
            ])
            ->values()
            ->all();

        return [
            'invoices_count' => $invoices->count(),
            'unique_wali_count' => $recipientsWithAccount->unique()->count(),
            'recipients_with_account' => $recipientsWithAccount->unique()->count(),
            'recipients_without_account' => $recipientsWithoutAccount,
            'wali_without_app_with_phone' => $waliWithoutAppWithPhone,
            'wa_estimated_unique_recipients' => $waPhonesEstimate->unique()->count(),
            'sample_recipients' => $sampleRecipients->all(),
            'by_class' => $byClass,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: bool, 1: bool}
     */
    private function validatedReminderChannels(array $validated): array
    {
        $sendApp = array_key_exists('send_app_notification', $validated)
            ? (bool) $validated['send_app_notification']
            : true;
        $sendWhatsapp = array_key_exists('send_whatsapp', $validated)
            ? (bool) $validated['send_whatsapp']
            : false;

        if (! $sendApp && ! $sendWhatsapp) {
            throw ValidationException::withMessages([
                'send_app_notification' => 'Pilih minimal satu: notifikasi aplikasi atau WhatsApp.',
            ]);
        }

        return [$sendApp, $sendWhatsapp];
    }

    private function sumRemainingAmount(Builder $query): float
    {
        $invoices = $query
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->get(['id', 'final_amount']);

        return (float) $invoices->sum(function (Invoice $invoice): float {
            return max(0, (float) $invoice->final_amount - (float) ($invoice->verified_paid_amount ?? 0));
        });
    }

    /**
     * @return array<int, array{label:string, amount:float}>
     */
    private function resolveInvoiceBreakdown(PaymentType $paymentType, float $amount, mixed $overrideBreakdown): array
    {
        $normalized = PaymentType::normalizeBreakdownItems($overrideBreakdown);
        $resolved = $normalized !== [] ? $normalized : $paymentType->buildBreakdownForAmount($amount);

        if ($resolved === []) {
            return [];
        }

        $sum = PaymentType::breakdownTotal($resolved);
        if (abs($sum - round($amount, 2)) > 0.01) {
            throw ValidationException::withMessages([
                'breakdown' => 'Total rincian harus sama dengan nominal tagihan.',
            ]);
        }

        return $resolved;
    }
}
