<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkRun;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\FeeSchedule;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
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

        return Inertia::render('admin/invoices/index', [
            'invoices' => $query->paginate(20)->withQueryString(),
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::orderBy('level_order')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'payment_type_id', 'academic_year_id', 'month', 'search', 'class_id']),
            'statusCounts' => [
                'all' => Invoice::count(),
                'pending' => Invoice::where('status', Invoice::STATUS_PENDING)->count(),
                'partial' => Invoice::where('status', Invoice::STATUS_PARTIAL)->count(),
                'paid' => Invoice::where('status', Invoice::STATUS_PAID)->count(),
                'overdue' => Invoice::where('status', Invoice::STATUS_OVERDUE)->count(),
            ],
        ]);
    }

    public function generate(Request $request): Response
    {
        $bulkRunsQuery = ImportRun::query()
            ->with('requestedBy:id,name')
            ->where('job_type', ImportRun::JOB_INVOICE_BULK_GENERATE)
            ->when($request->run_uploader_id, fn ($q, $id) => $q->where('requested_by', $id))
            ->latest('id');

        $uploaderIds = ImportRun::query()
            ->where('job_type', ImportRun::JOB_INVOICE_BULK_GENERATE)
            ->whereNotNull('requested_by')
            ->distinct()
            ->pluck('requested_by');

        return Inertia::render('admin/invoices/generate', [
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'is_recurring']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'students' => Student::query()
                ->where('status', Student::STATUS_ACTIVE)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'nis']),
            'bulkRuns' => $bulkRunsQuery->limit(20)->get(),
            'bulkUploaders' => User::query()->whereIn('id', $uploaderIds)->orderBy('name')->get(['id', 'name']),
            'runFilters' => $request->only(['run_uploader_id']),
        ]);
    }

    public function bulkGenerate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_type_id' => ['required', 'exists:payment_types,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'target_type' => ['required', Rule::in(['all', 'selected'])],
            'student_ids' => ['exclude_unless:target_type,selected', 'required', 'array', 'min:1'],
            'student_ids.*' => ['exclude_unless:target_type,selected', 'integer', 'exists:students,id'],
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
            'file_name' => 'bulk-generate-invoice',
            'file_path' => '-',
            'meta' => [
                ...$validated,
            ],
        ]);

        ProcessBulkRun::dispatch($run->id);

        return redirect()->route('admin.invoices.generate')
            ->with('success', 'Bulk generate tagihan diproses di background.');
    }

    public function retryBulkRun(ImportRun $importRun): RedirectResponse
    {
        abort_unless($importRun->job_type === ImportRun::JOB_INVOICE_BULK_GENERATE, 404);
        abort_unless($importRun->status === ImportRun::STATUS_FAILED, 422, 'Hanya job gagal yang bisa di-retry.');

        $targetStudentIds = $this->resolveInvoiceRetryTargetStudentIds($importRun->meta ?? []);
        if ($targetStudentIds === []) {
            return redirect()->route('admin.invoices.generate')
                ->with('error', 'Retry dilewati: tidak ada target santri aktif yang valid.');
        }

        $remainingStudentIds = $this->resolveInvoiceRetryStudentIds($importRun->meta ?? []);
        $isResendOnly = $remainingStudentIds === [];

        $retryMeta = [
            ...($importRun->meta ?? []),
            'target_type' => 'selected',
            'student_ids' => $isResendOnly ? $targetStudentIds : $remainingStudentIds,
            'resend_notification_on_existing' => true,
            'retry_mode' => $isResendOnly ? 'resend_existing_only' : 'create_missing_and_resend_existing',
        ];

        $retryRun = ImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportRun::TYPE_BULK,
            'job_type' => ImportRun::JOB_INVOICE_BULK_GENERATE,
            'status' => ImportRun::STATUS_QUEUED,
            'requested_by' => request()->user()?->id,
            'file_name' => $importRun->file_name,
            'file_path' => '-',
            'meta' => $retryMeta,
        ]);

        ProcessBulkRun::dispatch($retryRun->id);

        $message = $isResendOnly
            ? 'Retry mode resend diproses: invoice existing tidak dibuat ulang, hanya kirim ulang notifikasi.'
            : 'Retry bulk generate tagihan diproses di background.';

        return redirect()->route('admin.invoices.generate')->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int>
     */
    private function resolveInvoiceRetryTargetStudentIds(array $meta): array
    {
        $targetType = (string) ($meta['target_type'] ?? 'all');
        $selectedStudentIds = collect($meta['student_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        return Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->when($targetType === 'selected', fn ($q) => $q->whereIn('id', $selectedStudentIds->all()))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int>
     */
    private function resolveInvoiceRetryStudentIds(array $meta): array
    {
        $paymentTypeId = (int) ($meta['payment_type_id'] ?? 0);
        $academicYearId = (int) ($meta['academic_year_id'] ?? 0);
        $month = $meta['month'] ?? null;

        if ($paymentTypeId <= 0 || $academicYearId <= 0) {
            return [];
        }

        $targetStudentIds = collect($this->resolveInvoiceRetryTargetStudentIds($meta));

        if ($targetStudentIds->isEmpty()) {
            return [];
        }

        $existingStudentIds = Invoice::query()
            ->whereIn('student_id', $targetStudentIds->all())
            ->where('payment_type_id', $paymentTypeId)
            ->where('academic_year_id', $academicYearId)
            ->where('month', $month)
            ->pluck('student_id');

        return $targetStudentIds
            ->diff($existingStudentIds)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
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
        $this->notifyInvoiceCreatedTargets($invoice);

        return redirect()->route('admin.invoices.index')->with('success', 'Tagihan berhasil dibuat.');
    }

    public function show(Invoice $invoice): Response
    {
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

        return Inertia::render('admin/invoices/show', [
            'invoice' => $invoice,
        ]);
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            return redirect()->back()->with('error', 'Tagihan yang sudah lunas tidak bisa dibatalkan.');
        }

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        return redirect()->back()->with('success', 'Tagihan berhasil dibatalkan.');
    }

    private function resolveAmount(PaymentType $type, int $academicYearId, ?string $classLevel): float
    {
        $schedule = FeeSchedule::where('payment_type_id', $type->id)
            ->where('academic_year_id', $academicYearId)
            ->where(function ($q) use ($classLevel) {
                $q->where('class_level', $classLevel)->orWhereNull('class_level');
            })
            ->orderByRaw('class_level IS NULL ASC')
            ->first();

        return (float) ($schedule?->amount ?? $type->default_amount);
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

        $student->guardians
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn ($user) => $user->notify(new InvoiceCreatedNotification($invoice)));

        if ($student->user) {
            $student->user->notify(new InvoiceCreatedNotification($invoice));
        }
    }
}
