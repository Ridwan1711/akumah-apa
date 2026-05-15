<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EnrollmentTingkatSekolah;
use App\Models\FormalContinuationRound;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentFormalContinuationRequest;
use App\Models\TingkatSekolah;
use App\Models\User;
use App\Notifications\FormalContinuationStatusNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormalContinuationService
{
    public const TRANSITION_CODES = [
        TingkatSekolah::CODE_MTS_9,
        TingkatSekolah::CODE_MA_12,
    ];

    public function findOpenRequestForStudent(Student $student): ?StudentFormalContinuationRequest
    {
        return StudentFormalContinuationRequest::query()
            ->where('student_id', $student->id)
            ->whereIn('status', StudentFormalContinuationRequest::OPEN_STATUSES)
            ->with(['round', 'currentTingkatSekolah:id,name,code', 'sourceAcademicYear:id,name', 'targetAcademicYear:id,name'])
            ->latest('id')
            ->first();
    }

    public function latestForStudent(Student $student): ?StudentFormalContinuationRequest
    {
        return StudentFormalContinuationRequest::query()
            ->where('student_id', $student->id)
            ->with(['round', 'currentTingkatSekolah:id,name,code', 'sourceAcademicYear:id,name', 'targetAcademicYear:id,name'])
            ->latest('id')
            ->first();
    }

    public function roundForSourceYear(int $sourceAcademicYearId): ?FormalContinuationRound
    {
        return FormalContinuationRound::query()
            ->where('source_academic_year_id', $sourceAcademicYearId)
            ->with(['sourceAcademicYear:id,name,end_date', 'targetAcademicYear:id,name'])
            ->first();
    }

    /**
     * @return Collection<int, array{student: Student, enrollment: EnrollmentTingkatSekolah, tingkat: TingkatSekolah}>
     */
    public function eligibleStudents(int $sourceAcademicYearId): Collection
    {
        $enrollments = EnrollmentTingkatSekolah::query()
            ->where('academic_year_id', $sourceAcademicYearId)
            ->whereHas('tingkatSekolah', fn (Builder $q) => $q->whereIn('code', self::TRANSITION_CODES))
            ->whereHas('student', fn (Builder $q) => $q->where('status', Student::STATUS_ACTIVE))
            ->with([
                'student:id,full_name,nis,status,user_id',
                'tingkatSekolah:id,name,code',
            ])
            ->get();

        return $enrollments->map(fn (EnrollmentTingkatSekolah $row) => [
            'student' => $row->student,
            'enrollment' => $row,
            'tingkat' => $row->tingkatSekolah,
        ])->filter(fn (array $row) => $row['student'] !== null && $row['tingkat'] !== null)->values();
    }

    /**
     * @return array{round: FormalContinuationRound, created: int, skipped: int}
     */
    public function sendRound(
        int $sourceAcademicYearId,
        int $targetAcademicYearId,
        string $triggerType,
        ?User $sender = null,
    ): array {
        if ($sourceAcademicYearId === $targetAcademicYearId) {
            throw ValidationException::withMessages([
                'target_academic_year_id' => 'Tahun ajaran tujuan harus berbeda dari tahun sumber.',
            ]);
        }

        if ($this->roundForSourceYear($sourceAcademicYearId)) {
            throw ValidationException::withMessages([
                'source_academic_year_id' => 'Undangan untuk tahun ajaran ini sudah pernah dikirim.',
            ]);
        }

        $eligible = $this->eligibleStudents($sourceAcademicYearId);
        if ($eligible->isEmpty()) {
            throw ValidationException::withMessages([
                'source_academic_year_id' => 'Tidak ada santri aktif di tingkat MTs 9 atau MA 12 untuk tahun ajaran tersebut.',
            ]);
        }

        return DB::transaction(function () use ($sourceAcademicYearId, $targetAcademicYearId, $triggerType, $sender, $eligible) {
            $round = FormalContinuationRound::query()->create([
                'source_academic_year_id' => $sourceAcademicYearId,
                'target_academic_year_id' => $targetAcademicYearId,
                'trigger_type' => $triggerType,
                'sent_by_user_id' => $sender?->id,
                'sent_at' => now(),
                'eligible_count' => $eligible->count(),
                'requests_created' => 0,
            ]);

            $created = 0;
            foreach ($eligible as $row) {
                /** @var Student $student */
                $student = $row['student'];
                /** @var TingkatSekolah $tingkat */
                $tingkat = $row['tingkat'];

                $exists = StudentFormalContinuationRequest::query()
                    ->where('student_id', $student->id)
                    ->whereIn('status', StudentFormalContinuationRequest::OPEN_STATUSES)
                    ->exists();
                if ($exists) {
                    continue;
                }

                StudentFormalContinuationRequest::query()->create([
                    'formal_continuation_round_id' => $round->id,
                    'student_id' => $student->id,
                    'source_academic_year_id' => $sourceAcademicYearId,
                    'target_academic_year_id' => $targetAcademicYearId,
                    'current_tingkat_sekolah_id' => $tingkat->id,
                    'current_tingkat_code' => (string) $tingkat->code,
                    'status' => StudentFormalContinuationRequest::STATUS_AWAITING,
                    'initiated_by' => $triggerType === FormalContinuationRound::TRIGGER_MANUAL ? 'admin' : 'system',
                    'initiated_by_user_id' => $sender?->id,
                ]);
                $created++;

                $this->notifyStudentInvited($student, $round);
            }

            $round->update(['requests_created' => $created]);

            return [
                'round' => $round->fresh(['sourceAcademicYear', 'targetAcademicYear', 'sender:id,name']),
                'created' => $created,
                'skipped' => $eligible->count() - $created,
            ];
        });
    }

    /**
     * @return array{processed: int, rounds: int}
     */
    public function runFallbackTwoMonthsBeforeEnd(): array
    {
        $threshold = now()->addMonths(2)->toDateString();
        $years = AcademicYear::query()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', $threshold)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('end_date')
            ->get();

        $processed = 0;
        $roundsCreated = 0;

        foreach ($years as $sourceYear) {
            if ($this->roundForSourceYear((int) $sourceYear->id)) {
                continue;
            }

            $targetYear = $this->resolveNextAcademicYear($sourceYear);
            if ($targetYear === null) {
                continue;
            }

            try {
                $this->sendRound(
                    (int) $sourceYear->id,
                    (int) $targetYear->id,
                    FormalContinuationRound::TRIGGER_FALLBACK,
                );
                $roundsCreated++;
                $processed++;
            } catch (ValidationException) {
                continue;
            }
        }

        return ['processed' => $processed, 'rounds' => $roundsCreated];
    }

    public function submitSantriChoice(
        StudentFormalContinuationRequest $request,
        User $santriUser,
        string $choice,
    ): StudentFormalContinuationRequest {
        $this->assertAwaiting($request);
        $this->assertChoiceAllowed($request, $choice);

        $request->fill([
            'santri_choice' => $choice,
            'santri_user_id' => $santriUser->id,
            'santri_confirmed_at' => now(),
        ]);
        $request->save();

        return $this->afterChoiceUpdated($request);
    }

    public function submitWaliChoice(
        StudentFormalContinuationRequest $request,
        User $waliUser,
        Guardian $guardian,
        string $choice,
    ): StudentFormalContinuationRequest {
        $this->assertAwaiting($request);
        $this->assertChoiceAllowed($request, $choice);

        $request->fill([
            'wali_choice' => $choice,
            'wali_user_id' => $waliUser->id,
            'wali_guardian_id' => $guardian->id,
            'wali_confirmed_at' => now(),
        ]);
        $request->save();

        return $this->afterChoiceUpdated($request);
    }

    public function cancel(StudentFormalContinuationRequest $request, User $actor): StudentFormalContinuationRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'request' => 'Permohonan tidak dapat dibatalkan.',
            ]);
        }

        $request->update(['status' => StudentFormalContinuationRequest::STATUS_CANCELLED]);
        $request = $request->fresh(['student', 'round']);
        $this->notifyParties($request, 'cancelled');

        return $request;
    }

    public function approve(StudentFormalContinuationRequest $request, User $admin, ?string $adminNotes = null): StudentFormalContinuationRequest
    {
        if ($request->status !== StudentFormalContinuationRequest::STATUS_PENDING_ADMIN) {
            throw ValidationException::withMessages([
                'request' => 'Hanya permohonan menunggu admin yang dapat disetujui.',
            ]);
        }

        DB::transaction(function () use ($request, $admin, $adminNotes) {
            $student = $request->student()->lockForUpdate()->first();
            $this->applyEnrollmentForChoice($student, $request);

            $request->update([
                'status' => StudentFormalContinuationRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);
        });

        $request = $request->fresh(['student', 'round', 'currentTingkatSekolah']);
        $this->notifyParties($request, 'approved');

        return $request;
    }

    public function reject(
        StudentFormalContinuationRequest $request,
        User $admin,
        string $rejectionReason,
        ?string $adminNotes = null,
    ): StudentFormalContinuationRequest {
        if ($request->status !== StudentFormalContinuationRequest::STATUS_PENDING_ADMIN) {
            throw ValidationException::withMessages([
                'request' => 'Hanya permohonan menunggu admin yang dapat ditolak.',
            ]);
        }

        $request->update([
            'status' => StudentFormalContinuationRequest::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
            'admin_notes' => $adminNotes,
        ]);

        $request = $request->fresh(['student', 'round']);
        $this->notifyParties($request, 'rejected');

        return $request;
    }

    private function afterChoiceUpdated(StudentFormalContinuationRequest $request): StudentFormalContinuationRequest
    {
        $request->refresh();

        if (! $request->bothChoicesSubmitted()) {
            $this->notifyCounterpartyPending($request);

            return $request->load(['round', 'currentTingkatSekolah', 'sourceAcademicYear', 'targetAcademicYear']);
        }

        $resolved = $request->wali_choice ?? $request->santri_choice;
        $request->update([
            'resolved_choice' => $resolved,
            'status' => StudentFormalContinuationRequest::STATUS_PENDING_ADMIN,
        ]);

        $request = $request->fresh(['student', 'round']);
        $this->notifyParties($request, 'pending_admin');
        $this->notifyAdminsPending($request);

        return $request->load(['round', 'currentTingkatSekolah', 'sourceAcademicYear', 'targetAcademicYear']);
    }

    private function applyEnrollmentForChoice(Student $student, StudentFormalContinuationRequest $request): void
    {
        $targetTingkatId = $this->resolveTargetTingkatId($request);
        if ($targetTingkatId === null) {
            throw ValidationException::withMessages([
                'resolved_choice' => 'Tingkat tujuan tidak ditemukan di master data.',
            ]);
        }

        EnrollmentTingkatSekolah::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $request->target_academic_year_id,
            ],
            [
                'tingkat_sekolah_id' => $targetTingkatId,
            ],
        );

        $isKuliah = TingkatSekolah::query()->whereKey($targetTingkatId)->value('code') === TingkatSekolah::CODE_KULIAH;
        $student->update([
            'is_kuliah' => $isKuliah,
            'status' => Student::STATUS_ACTIVE,
        ]);
    }

    private function resolveTargetTingkatId(StudentFormalContinuationRequest $request): ?int
    {
        $choice = $request->resolved_choice ?? $request->wali_choice ?? $request->santri_choice;

        $code = match ($choice) {
            StudentFormalContinuationRequest::CHOICE_MA_10 => TingkatSekolah::CODE_MA_10,
            StudentFormalContinuationRequest::CHOICE_KULIAH => TingkatSekolah::CODE_KULIAH,
            default => null,
        };

        if ($code === null) {
            return null;
        }

        return TingkatSekolah::query()->where('code', $code)->value('id');
    }

    private function resolveNextAcademicYear(AcademicYear $source): ?AcademicYear
    {
        if ($source->end_date === null) {
            return AcademicYear::query()
                ->where('id', '>', $source->id)
                ->orderBy('id')
                ->first();
        }

        return AcademicYear::query()
            ->where('start_date', '>', $source->end_date)
            ->orderBy('start_date')
            ->first();
    }

    private function assertAwaiting(StudentFormalContinuationRequest $request): void
    {
        if ($request->status !== StudentFormalContinuationRequest::STATUS_AWAITING) {
            throw ValidationException::withMessages([
                'request' => 'Permohonan tidak dalam tahap konfirmasi.',
            ]);
        }
    }

    private function assertChoiceAllowed(StudentFormalContinuationRequest $request, string $choice): void
    {
        $allowed = StudentFormalContinuationRequest::allowedChoicesForCode($request->current_tingkat_code);
        if (! in_array($choice, $allowed, true)) {
            throw ValidationException::withMessages([
                'choice' => 'Pilihan tidak valid untuk tingkat saat ini.',
            ]);
        }
    }

    private function notifyStudentInvited(Student $student, FormalContinuationRound $round): void
    {
        $student->loadMissing('user', 'guardians.user');
        $event = 'invited';

        $student->user?->notify(new FormalContinuationStatusNotification($round, $student, $event));
        $student->guardians
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new FormalContinuationStatusNotification($round, $student, $event)));
    }

    private function notifyCounterpartyPending(StudentFormalContinuationRequest $request): void
    {
        if ($request->santri_choice !== null && $request->wali_choice === null) {
            $request->loadMissing('student.guardians.user');
            $request->student?->guardians
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->each(fn (User $u) => $u->notify(new FormalContinuationStatusNotification($request->round, $request->student, 'wali_pending', $request)));
        }
        if ($request->wali_choice !== null && $request->santri_choice === null) {
            $request->loadMissing('student.user');
            $request->student?->user?->notify(new FormalContinuationStatusNotification($request->round, $request->student, 'santri_pending', $request));
        }
    }

    private function notifyParties(StudentFormalContinuationRequest $request, string $event): void
    {
        $request->loadMissing('student.user', 'student.guardians.user', 'round');
        $request->student?->user?->notify(new FormalContinuationStatusNotification($request->round, $request->student, $event, $request));
        $request->student?->guardians
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn (User $u) => $u->notify(new FormalContinuationStatusNotification($request->round, $request->student, $event, $request)));
    }

    private function notifyAdminsPending(StudentFormalContinuationRequest $request): void
    {
        User::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK]))
            ->get()
            ->each(fn (User $u) => $u->notify(new FormalContinuationStatusNotification($request->round, $request->student, 'admin_pending', $request)));
    }
}
