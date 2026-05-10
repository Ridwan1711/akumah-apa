<?php

namespace App\Jobs;

use App\Models\AcademicPeriod;
use App\Models\Diniyyah\ClassPromotion;
use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\StudentClassEnrollment;
use App\Models\Guardian;
use App\Models\ImportRun;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Notifications\BulkRunFinishedNotification;
use App\Services\Finance\InvoiceImportSupport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class ProcessBulkRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $importRunId
    ) {}

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return 'process_bulk_run_'.$this->importRunId;
    }

    public function handle(): void
    {
        $run = ImportRun::query()->find($this->importRunId);
        if (! $run || $run->isFinal()) {
            return;
        }

        $run->update([
            'status' => ImportRun::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            match ($run->job_type) {
                ImportRun::JOB_INVOICE_BULK_GENERATE => $this->processInvoiceBulkGenerate($run),
                ImportRun::JOB_CLASS_PROMOTION => $this->processClassPromotion($run),
                ImportRun::JOB_ACCOUNT_GENERATE_STUDENTS => $this->processAccountGenerateStudents($run),
                ImportRun::JOB_ACCOUNT_GENERATE_GUARDIANS => $this->processAccountGenerateGuardians($run),
                default => throw new \RuntimeException('Unsupported bulk job type: '.$run->job_type),
            };

            $run->refresh();
            $run->update([
                'status' => ImportRun::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);
            $this->notifyRunResult($run);
        } catch (Throwable $e) {
            $run->update([
                'status' => ImportRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            $this->notifyRunResult($run);
            throw $e;
        }
    }

    protected function processInvoiceBulkGenerate(ImportRun $run): void
    {
        $meta = $run->meta ?? [];
        $paymentTypeId = (int) ($meta['payment_type_id'] ?? 0);
        $academicYearId = (int) ($meta['academic_year_id'] ?? 0);
        $targetType = $meta['target_type'] ?? 'all';
        $selectedStudentIds = collect($meta['student_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $month = $meta['month'] ?? null;
        $dueDate = $meta['due_date'] ?? null;
        $generatedBy = (int) ($run->requested_by ?? 0);
        $overrideBreakdown = $meta['breakdown'] ?? null;
        $resendNotificationOnExisting = filter_var(
            $meta['resend_notification_on_existing'] ?? $meta['send_notification_for_existing'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $retryMode = (string) ($meta['retry_mode'] ?? '');
        $resendOnly = $retryMode === 'resend_existing_only';
        $paymentType = PaymentType::findOrFail($paymentTypeId);
        $students = Student::query()
            ->where('status', Student::STATUS_ACTIVE)
            ->when($targetType === 'selected', fn ($query) => $query->whereIn('id', $selectedStudentIds))
            ->get();

        $run->update(['total_rows' => $students->count()]);

        foreach ($students as $student) {
            /** @var Student $student */
            if ($resendOnly) {
                $existingInvoice = Invoice::query()
                    ->where('student_id', $student->id)
                    ->where('payment_type_id', $paymentTypeId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('month', $month)
                    ->first();
                if ($resendNotificationOnExisting && $existingInvoice) {
                    InvoiceImportSupport::notifyInvoiceCreatedTargets($existingInvoice);
                }
                $this->incrementRunCounters($run, 'skipped');

                continue;
            }

            $amount = $this->resolveAmount($paymentType, $student);
            if ($amount === null) {
                $this->incrementRunCounters($run, 'skipped');

                continue;
            }
            $discount = InvoiceImportSupport::studentMasterDiscount($student->id, $paymentTypeId, $academicYearId, $amount);
            $breakdown = InvoiceImportSupport::resolveBreakdownScaled($paymentType, $amount, $overrideBreakdown);

            try {
                $invoice = Invoice::create([
                    'invoice_number' => Invoice::generateNumber($paymentTypeId, $month, $academicYearId, $student->full_name),
                    'student_id' => $student->id,
                    'payment_type_id' => $paymentTypeId,
                    'academic_year_id' => $academicYearId,
                    'month' => $month,
                    'amount' => $amount,
                    'discount_amount' => $discount,
                    'final_amount' => $amount - $discount,
                    'breakdown' => $breakdown,
                    'status' => Invoice::STATUS_PENDING,
                    'due_date' => $dueDate,
                    'generated_by' => $generatedBy > 0 ? $generatedBy : null,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    if ($resendNotificationOnExisting) {
                        $existingInvoice = Invoice::query()
                            ->where('student_id', $student->id)
                            ->where('payment_type_id', $paymentTypeId)
                            ->where('academic_year_id', $academicYearId)
                            ->where('month', $month)
                            ->first();
                        if ($existingInvoice) {
                            InvoiceImportSupport::notifyInvoiceCreatedTargets($existingInvoice);
                        }
                    }
                    $this->incrementRunCounters($run, 'skipped');

                    continue;
                }
                throw $e;
            }
            InvoiceImportSupport::notifyInvoiceCreatedTargets($invoice);

            $this->incrementRunCounters($run, 'created');
        }
    }

    protected function processClassPromotion(ImportRun $run): void
    {
        $promotions = $run->meta['promotions'] ?? [];
        $sourceClassId = (int) ($run->meta['source_class_id'] ?? 0);
        $periodId = (int) ($run->meta['period_id'] ?? 0);
        $period = $periodId > 0 ? AcademicPeriod::find($periodId) : null;

        if ($period && ! $period->isSemesterTwo()) {
            throw new \RuntimeException('Kenaikan kelas hanya diperbolehkan pada periode semester 2 (Akhirussanah).');
        }

        $run->update(['total_rows' => count($promotions)]);

        foreach ($promotions as $item) {
            $student = Student::find($item['student_id'] ?? null);
            if (! $student) {
                $this->incrementRunCounters($run, 'failed');

                continue;
            }

            $action = $item['action'] ?? null;
            if ($action === 'promote' && ! empty($item['target_class_id'])) {
                $targetId = (int) $item['target_class_id'];
                $targetClass = SchoolClass::query()->find($targetId);
                if (! $targetClass) {
                    $this->incrementRunCounters($run, 'failed');

                    continue;
                }
                if (! $targetClass->acceptsStudentGender($student->gender)) {
                    $this->incrementRunCounters($run, 'failed');

                    continue;
                }

                if ($periodId > 0 && $sourceClassId > 0) {
                    ClassPromotion::query()->updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'period_id' => $periodId,
                        ],
                        [
                            'from_class_id' => $sourceClassId,
                            'to_class_id' => $targetId,
                            'status' => ClassPromotion::STATUS_APPROVED,
                            'approved_by' => $run->requested_by,
                            'notes' => null,
                        ]
                    );

                    StudentClassEnrollment::query()->updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'period_id' => $periodId,
                        ],
                        [
                            'class_id' => $targetId,
                        ]
                    );
                }

                $student->update(['current_class_id' => $targetId]);
                $this->incrementRunCounters($run, 'updated');
            } elseif ($action === 'stay') {
                $this->incrementRunCounters($run, 'skipped');
            } elseif ($action === 'graduate') {
                $student->update([
                    'status' => Student::STATUS_ALUMNI,
                    'current_class_id' => null,
                ]);
                $this->incrementRunCounters($run, 'updated');
            } else {
                $this->incrementRunCounters($run, 'failed');
            }
        }
    }

    protected function processAccountGenerateStudents(ImportRun $run): void
    {
        $studentIds = array_values($run->meta['student_ids'] ?? []);
        $includeWali = filter_var($run->meta['include_wali_accounts'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $role = Role::query()->where('name', Role::SANTRI)->firstOrFail();
        $students = Student::query()->whereIn('id', $studentIds)->whereNull('user_id')->get();
        $results = [];
        $waliResults = [];

        $run->update(['total_rows' => count($studentIds)]);

        foreach ($students as $student) {
            /** @var Student $student */
            $password = Str::random(8);
            $user = User::create([
                'name' => $student->full_name,
                'username' => $student->nis,
                'email' => $student->nis.'@santri.siakad.test',
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->roles()->sync([$role->id]);
            $student->update(['user_id' => $user->id]);
            $results[] = [
                'name' => $student->full_name,
                'nis' => $student->nis,
                'username' => $student->nis,
                'password' => $password,
            ];
            $this->incrementRunCounters($run, 'created');

            if ($includeWali) {
                $this->ensureWaliAccountsForStudent($run, $student, $waliResults);
            }
        }

        $skipped = max(0, count($studentIds) - count($students));
        if ($skipped > 0) {
            $run->update([
                'processed_rows' => $run->processed_rows + $skipped,
                'skipped_count' => $run->skipped_count + $skipped,
            ]);
        }

        $run->update([
            'result_payload' => [
                'generated_accounts' => $results,
                'generated_wali_accounts' => $waliResults,
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $waliResults
     */
    protected function ensureWaliAccountsForStudent(ImportRun $run, Student $student, array &$waliResults): void
    {
        if (! $student->guardians()->exists()) {
            $placeholder = Guardian::create([
                'full_name' => 'Wali '.$student->full_name,
                'relationship' => 'wali',
            ]);
            $student->guardians()->attach($placeholder->id, ['relationship' => 'wali']);
        }

        $pending = $student->guardians()->whereNull('guardians.user_id')->get();
        foreach ($pending as $guardian) {
            $row = $this->createWaliUserForGuardian($guardian, requireProfileCompletion: true);
            if ($row !== null) {
                $waliResults[] = $row;
                $this->incrementRunCounters($run, 'created');
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function createWaliUserForGuardian(Guardian $guardian, bool $requireProfileCompletion): ?array
    {
        $guardian->refresh();
        if ($guardian->user_id !== null) {
            return null;
        }

        $role = Role::query()->where('name', Role::WALI_SANTRI)->firstOrFail();
        $guardian->load('students:id,nis');
        $firstStudent = $guardian->students->first();
        $password = Str::random(8);
        $username = 'wali_'.($firstStudent?->nis ?? $guardian->id).'_'.$guardian->id;

        $user = User::create([
            'name' => $guardian->full_name,
            'username' => $username,
            'email' => $username.'@wali.siakad.test',
            'password' => Hash::make($password),
            'is_active' => true,
            'must_change_password' => true,
            'must_complete_profile' => $requireProfileCompletion,
        ]);
        $user->roles()->sync([$role->id]);
        $guardian->update(['user_id' => $user->id]);

        return [
            'guardian_name' => $guardian->full_name,
            'student_nis' => $firstStudent?->nis ?? '-',
            'username' => $username,
            'password' => $password,
        ];
    }

    protected function processAccountGenerateGuardians(ImportRun $run): void
    {
        $guardianIds = array_values($run->meta['guardian_ids'] ?? []);
        $guardians = Guardian::query()->whereIn('id', $guardianIds)->whereNull('user_id')->with('students:id,nis')->get();
        $results = [];

        $run->update(['total_rows' => count($guardianIds)]);

        foreach ($guardians as $guardian) {
            /** @var Guardian $guardian */
            $row = $this->createWaliUserForGuardian($guardian, requireProfileCompletion: false);
            if ($row === null) {
                continue;
            }
            $results[] = $row;
            $this->incrementRunCounters($run, 'created');
        }

        $skipped = max(0, count($guardianIds) - count($guardians));
        if ($skipped > 0) {
            $run->update([
                'processed_rows' => $run->processed_rows + $skipped,
                'skipped_count' => $run->skipped_count + $skipped,
            ]);
        }

        $run->update(['result_payload' => ['generated_accounts' => $results]]);
    }

    protected function incrementRunCounters(ImportRun $run, string $type): void
    {
        $run->refresh();
        $updates = ['processed_rows' => $run->processed_rows + 1];
        if ($type === 'created') {
            $updates['created_count'] = $run->created_count + 1;
        } elseif ($type === 'updated') {
            $updates['updated_count'] = $run->updated_count + 1;
        } elseif ($type === 'skipped') {
            $updates['skipped_count'] = $run->skipped_count + 1;
        } else {
            $updates['failed_count'] = $run->failed_count + 1;
        }
        $run->update($updates);
    }

    protected function resolveAmount(PaymentType $type, Student $student): ?float
    {
        if ($student->is_kuliah) {
            return $type->kuliah_amount !== null ? (float) $type->kuliah_amount : null;
        }

        return (float) $type->default_amount;
    }

    protected function notifyRunResult(ImportRun $run): void
    {
        $user = $run->requestedBy;
        if (! $user) {
            return;
        }

        $user->notify(new BulkRunFinishedNotification($run));
    }

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return $sqlState === '23505' // PostgreSQL unique_violation
            || $sqlState === '23000' // ANSI/MySQL integrity violation
            || $driverCode === '1062'; // MySQL duplicate entry
    }
}
