<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkGenerateInvoiceRequest;
use App\Jobs\ProcessBulkRun;
use App\Models\AcademicYear;
use App\Models\Diniyyah\SchoolClass;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Student;
use App\Models\StudentPosition;
use App\Models\TingkatSekolah;
use App\Models\User;
use App\Services\Authorization\InvoiceVisibilityScope;
use App\Services\Finance\DispatchInvoiceRemindersAction;
use App\Services\Finance\FinanceInvoicePricingResolver;
use App\Services\Finance\InvoiceImportSupport;
use App\Services\Finance\StudentInvoiceCoverPeriod;
use App\Support\Authorization\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceVisibilityScope $invoiceVisibilityScope): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        // Base invoice query carrying all filter conditions. Reused both as a
        // sub-query for paginating distinct students AND for hydrating each
        // student's filtered invoices below.
        $invoiceFilterQuery = Invoice::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_type_id, fn ($q, $id) => $q->where('payment_type_id', $id))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->month, fn ($q, $m) => $q->where('month', $m))
            ->when($request->search, fn ($q, $s) => $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'ilike', "%{$s}%")->orWhere('nis', 'like', "%{$s}%")))
            ->when(
                $request->filled('tingkat_sekolah_id'),
                fn ($q) => $q->forFormalTingkat((int) $request->tingkat_sekolah_id)
            )
            ->when(
                ! $request->filled('tingkat_sekolah_id') && $request->filled('class_id'),
                fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('current_class_id', (int) $request->class_id))
            )
            ->when(
                $request->filled('division_code'),
                fn ($q) => $q->whereHas(
                    'student.activePositions',
                    fn ($positionQuery) => $positionQuery->where('division_code', (string) $request->string('division_code'))
                )
            );

        $invoiceVisibilityScope->applyToInvoiceQuery($invoiceFilterQuery, $user);

        $totalInvoiceCount = (clone $invoiceFilterQuery)->count();

        // Paginate distinct students that own at least one matching invoice.
        $studentPaginator = Student::query()
            ->select('students.*')
            ->whereIn('students.id', (clone $invoiceFilterQuery)->select('invoices.student_id'))
            ->with(['currentClass:id,name'])
            ->orderByDesc(
                Invoice::query()
                    ->select('created_at')
                    ->whereColumn('invoices.student_id', 'students.id')
                    ->latest('created_at')
                    ->limit(1)
            )
            ->orderBy('students.full_name')
            ->paginate(20)
            ->withQueryString();

        $studentIds = $studentPaginator->getCollection()->pluck('id')->all();

        $invoicesByStudent = (clone $invoiceFilterQuery)
            ->whereIn('invoices.student_id', $studentIds)
            ->with([
                'paymentType:id,name,code,category',
                'academicYear:id,name',
                'tingkatSekolah:id,name,code',
            ])
            ->withCount('payments')
            ->withSum([
                'payments as verified_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', \App\Models\Payment::STATUS_VERIFIED),
            ], 'amount')
            ->withSum([
                'payments as pending_paid_amount' => fn ($paymentQuery) => $paymentQuery->where('status', \App\Models\Payment::STATUS_PENDING),
            ], 'amount')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Invoice $invoice) {
                $invoice->total_paid = (float) ($invoice->verified_paid_amount ?? 0);
                $invoice->pending_amount = (float) ($invoice->pending_paid_amount ?? 0);
                $invoice->remaining = max(0, (float) $invoice->final_amount - $invoice->total_paid);

                return $invoice;
            })
            ->groupBy('student_id');

        $studentPaginator->getCollection()->transform(function (Student $student) use ($invoicesByStudent) {
            $invoices = ($invoicesByStudent->get($student->id) ?? collect())->values();
            $firstInvoice = $invoices->first();

            return [
                'student_id' => $student->id,
                'student_nis' => $student->nis,
                'student_name' => $student->full_name,
                'class_name' => $student->currentClass?->name,
                'tingkat_formal_name' => $firstInvoice instanceof Invoice ? ($firstInvoice->tingkatSekolah?->name ?? null) : null,
                'invoice_count' => $invoices->count(),
                'total_amount' => (float) $invoices->sum(fn (Invoice $i) => (float) $i->final_amount),
                'total_paid' => (float) $invoices->sum(fn (Invoice $i) => (float) ($i->total_paid ?? 0)),
                'pending_amount' => (float) $invoices->sum(fn (Invoice $i) => (float) ($i->pending_amount ?? 0)),
                'total_remaining' => (float) $invoices->sum(fn (Invoice $i) => (float) ($i->remaining ?? $i->final_amount)),
                'invoices' => $invoices,
            ];
        });

        return Inertia::render('admin/invoices/index', [
            'studentGroups' => $studentPaginator,
            'totalInvoiceCount' => $totalInvoiceCount,
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('order')->orderBy('name')->get(['id', 'name']),
            'divisionOptions' => StudentPosition::query()
                ->whereNotNull('division_code')
                ->where('division_code', '!=', '')
                ->distinct()
                ->orderBy('division_code')
                ->pluck('division_code')
                ->values(),
            'tingkatSekolahs' => TingkatSekolah::query()->orderBy('order')->orderBy('name')->get(['id', 'name', 'code', 'group']),
            'filters' => $request->only(['status', 'payment_type_id', 'academic_year_id', 'month', 'search', 'class_id', 'tingkat_sekolah_id', 'division_code']),
            'statusCounts' => $this->scopedInvoiceStatusCounts($invoiceVisibilityScope, $user),
            'invoiceImportRuns' => ImportRun::query()
                ->with('requestedBy:id,name')
                ->where('type', ImportRun::TYPE_INVOICES)
                ->where('job_type', ImportRun::JOB_INVOICE_IMPORT)
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function scopedInvoiceStatusCounts(InvoiceVisibilityScope $invoiceVisibilityScope, User $user): array
    {
        $base = Invoice::query();
        $invoiceVisibilityScope->applyToInvoiceQuery($base, $user);

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', Invoice::STATUS_PENDING)->count(),
            'partial' => (clone $base)->where('status', Invoice::STATUS_PARTIAL)->count(),
            'paid' => (clone $base)->where('status', Invoice::STATUS_PAID)->count(),
            'overdue' => (clone $base)->where('status', Invoice::STATUS_OVERDUE)->count(),
        ];
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
            'paymentTypes' => PaymentType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'category', 'is_recurring', 'default_breakdown']),
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

    public function previewBulkGenerate(BulkGenerateInvoiceRequest $request): JsonResponse
    {
        $validated = $this->normalizedBulkGeneratePayload($request->validated());

        $paymentType = PaymentType::query()->findOrFail((int) $validated['payment_type_id']);
        $academicYear = AcademicYear::query()->findOrFail((int) $validated['academic_year_id']);

        $studentQuery = Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->when(
                $validated['target_type'] === 'selected',
                fn ($query) => $query->whereIn('id', $validated['student_ids'] ?? [])
            );

        $targetStudentCount = (clone $studentQuery)->count();

        $pricing = app(FinanceInvoicePricingResolver::class);
        $wouldSkipCount = 0;
        $withoutFormalEnrollment = 0;

        foreach ((clone $studentQuery)->cursor() as $studentRow) {
            /** @var Student $studentRow */
            if ($studentRow->formalTingkatEnrollmentForYear((int) $academicYear->id) === null) {
                $withoutFormalEnrollment++;
            }
            $resolved = $pricing->resolveAmountAndRule($paymentType, $studentRow, (int) $academicYear->id);
            if ($resolved['amount'] === null || $resolved['amount'] <= 0) {
                $wouldSkipCount++;
            }
        }

        $monthLabel = $this->resolveIndonesianMonthLabel($validated['month'] ?? null);

        return response()->json([
            'target_student_count' => $targetStudentCount,
            'would_skip_invoice_count' => $wouldSkipCount,
            'students_without_formal_enrollment_count' => $withoutFormalEnrollment,
            /** @deprecated gunakan would_skip_invoice_count */
            'kuliah_without_tariff_count' => $wouldSkipCount,
            'summary' => [
                'payment_type_name' => $paymentType->name,
                'payment_type_code' => $paymentType->code,
                'academic_year_name' => $academicYear->name,
                'month_label' => $monthLabel,
                'due_date' => $validated['due_date'],
                'target_type' => $validated['target_type'],
                'send_notification_for_existing' => $validated['send_notification_for_existing'],
            ],
        ]);
    }

    public function bulkGenerate(BulkGenerateInvoiceRequest $request): RedirectResponse
    {
        $validated = $this->normalizedBulkGeneratePayload($request->validated());

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
            'breakdown' => ['nullable', 'array'],
            'breakdown.*.label' => ['required_with:breakdown', 'string', 'max:120'],
            'breakdown.*.amount' => ['required_with:breakdown', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $paymentType = PaymentType::query()->findOrFail((int) $validated['payment_type_id']);
        try {
            $breakdown = InvoiceImportSupport::resolveBreakdownForInvoice(
                $paymentType,
                (float) $validated['amount'],
                $validated['breakdown'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'breakdown' => $e->getMessage(),
            ]);
        }

        $discount = InvoiceImportSupport::studentMasterDiscount(
            (int) $validated['student_id'],
            (int) $validated['payment_type_id'],
            (int) $validated['academic_year_id'],
            (float) $validated['amount']
        );

        $student = Student::query()->findOrFail((int) $validated['student_id']);
        $academicYear = AcademicYear::query()->findOrFail((int) $validated['academic_year_id']);
        $invoiceMonth = isset($validated['month']) ? (int) $validated['month'] : null;
        if (StudentInvoiceCoverPeriod::isPeriodAfterInactive($student, $academicYear, $invoiceMonth)) {
            throw ValidationException::withMessages([
                'academic_year_id' => StudentInvoiceCoverPeriod::userFacingMessage(),
            ]);
        }

        $tingkatSnapshot = $student->formalTingkatEnrollmentForYear((int) $validated['academic_year_id'])?->tingkat_sekolah_id;
        if ($tingkatSnapshot === null && $student->is_kuliah) {
            $tingkatSnapshot = TingkatSekolah::query()
                ->where('code', TingkatSekolah::CODE_KULIAH)
                ->value('id');
        }

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateNumber(),
            'student_id' => $validated['student_id'],
            'tingkat_sekolah_id' => $tingkatSnapshot,
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
        InvoiceImportSupport::notifyInvoiceCreatedTargets($invoice);

        return redirect()->route('admin.invoices.index')->with('success', 'Tagihan berhasil dibuat.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizedBulkGeneratePayload(array $validated): array
    {
        $validated['send_notification_for_existing'] = (bool) ($validated['send_notification_for_existing'] ?? true);

        return $validated;
    }

    private function resolveIndonesianMonthLabel(null|int|string $month): ?string
    {
        if ($month === null || $month === '') {
            return null;
        }

        $index = (int) $month;
        $names = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $names[$index] ?? null;
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load([
            'student:id,nis,full_name,current_class_id',
            'student.currentClass:id,name',
            'tingkatSekolah:id,name,code,group',
            'paymentType:id,name,code,category,default_breakdown',
            'academicYear:id,name',
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
            'payments.verifier:id,name',
        ]);

        $invoice->total_paid = $invoice->totalPaid();
        $invoice->pending_amount = $invoice->pendingAmount();
        $invoice->remaining = $invoice->remainingAmount();
        $invoice->breakdown_items = $invoice->resolvedBreakdown();

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

    public function sendReminder(
        Request $request,
        Invoice $invoice,
        InvoiceVisibilityScope $invoiceVisibilityScope,
        DispatchInvoiceRemindersAction $dispatchInvoiceRemindersAction,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null && $user->hasPermission(Permissions::INVOICE_REMINDER_SEND), 403);

        $visible = Invoice::query()->whereKey($invoice->id);
        $invoiceVisibilityScope->applyToInvoiceQuery($visible, $user);
        abort_unless($visible->exists(), 403, 'Tagihan ini di luar cakupan akses Anda.');

        if (in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            return redirect()->back()->with('error', 'Tagihan lunas atau dibatalkan tidak dapat dikirim pengingat.');
        }

        try {
            $validated = $request->validate([
                'message' => ['nullable', 'string', 'max:500'],
            ]);

            // JSON/Inertia: boolean false harus tetap terbaca (hindari "sometimes" + validated yang kosong).
            $sendApp = $request->has('send_app_notification')
                ? $request->boolean('send_app_notification')
                : true;
            $sendWhatsapp = $request->boolean('send_whatsapp');

            if (! $sendApp && ! $sendWhatsapp) {
                throw ValidationException::withMessages([
                    'send_app_notification' => 'Pilih minimal satu: notifikasi aplikasi atau WhatsApp.',
                ]);
            }

            $invoice->loadMissing('student.guardians.user', 'student.user:id,whatsapp_phone', 'paymentType');
            $student = $invoice->student;
            $guardians = $student?->guardians ?? collect();
            $waliWithAccount = (int) $guardians->filter(fn ($g) => $g->user !== null)->count();
            $waliWithoutAccount = (int) $guardians->filter(fn ($g) => $g->user === null)->count();
            $waEnabled = (bool) config('services.wa.enabled');

            $result = $dispatchInvoiceRemindersAction->run(
                collect([$invoice]),
                $validated['message'] ?? null,
                $user->id,
                $sendApp,
                $sendWhatsapp,
            );

            if ($result['sent_count'] === 0 && $result['wa_queued'] === 0) {
                $hint = [];
                $emNoHp = is_array($student?->em_profile)
                    ? (string) ($student->em_profile['no_hp'] ?? '')
                    : '';
                $hasEmNoHp = trim($emNoHp) !== '';

                if ($sendApp && $waliWithAccount === 0) {
                    if ($student?->user_id === null) {
                        $hint[] = 'tidak ada wali ber-akun dan santri belum dihubungkan ke akun login (user_id kosong)';
                    } else {
                        $hint[] = 'tidak ada wali ber-akun; pastikan role santri aktif untuk notifikasi ke akun santri';
                    }
                }
                if ($sendWhatsapp) {
                    if (! $waEnabled) {
                        $hint[] = 'WhatsApp nonaktif (WA_ENABLED=false di server)';
                    } elseif ($waliWithAccount === 0 && $waliWithoutAccount === 0) {
                        $hint[] = $hasEmNoHp
                            ? 'nomor di profil (No HP Santri) tidak valid untuk WA atau tidak ter-normalisasi'
                            : 'tidak ada wali dan No HP Santri (Profil EM) / WhatsApp di akun kosong';
                    } else {
                        $hint[] = 'nomor WA tidak ter-resolve (cek WhatsApp di akun, telepon wali, atau WA_ALLOW_FALLBACK_RECIPIENT)';
                    }
                }

                $detail = $hint !== [] ? ' Penyebab: '.implode('; ', $hint).'.' : '';
                Log::warning('invoice_send_reminder_no_recipients', [
                    'invoice_id' => $invoice->id,
                    'student_id' => $student?->id,
                    'student_user_id' => $student?->user_id,
                    'em_profile_has_no_hp' => $hasEmNoHp,
                    'send_app' => $sendApp,
                    'send_whatsapp' => $sendWhatsapp,
                    'wa_enabled' => $waEnabled,
                    'wali_with_account' => $waliWithAccount,
                    'wali_without_account' => $waliWithoutAccount,
                ]);

                return redirect()->back()->with(
                    'warning',
                    'Tidak ada penerima: tidak ada notifikasi aplikasi maupun antrean WA yang dibuat.'.$detail,
                );
            }

            $parts = [];
            if ($sendApp) {
                $parts[] = 'notifikasi aplikasi: '.$result['sent_count'];
            }
            if ($sendWhatsapp) {
                $parts[] = 'antrian WA: '.$result['wa_queued'];
            }

            Log::info('invoice_send_reminder_ok', [
                'invoice_id' => $invoice->id,
                'send_app' => $sendApp,
                'send_whatsapp' => $sendWhatsapp,
                'sent_count' => $result['sent_count'],
                'wa_queued' => $result['wa_queued'],
            ]);

            return redirect()->back()->with('success', 'Pengingat tagihan terkirim ('.implode(', ', $parts).').');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('invoice_send_reminder_failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with(
                'error',
                'Gagal mengirim pengingat: '.$e->getMessage(),
            );
        }
    }

    public function updateBreakdown(Request $request, Invoice $invoice): RedirectResponse
    {
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

        return redirect()->back()->with('success', 'Rincian tagihan berhasil diperbarui.');
    }
}
