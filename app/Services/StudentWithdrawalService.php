<?php

namespace App\Services;

use App\Models\DormAssignment;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentWithdrawalRequest;
use App\Models\User;
use App\Notifications\StudentWithdrawalStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentWithdrawalService
{
    public function findOpenRequest(Student $student): ?StudentWithdrawalRequest
    {
        return StudentWithdrawalRequest::query()
            ->where('student_id', $student->id)
            ->whereIn('status', StudentWithdrawalRequest::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    public function latestForStudent(Student $student): ?StudentWithdrawalRequest
    {
        return StudentWithdrawalRequest::query()
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{request: StudentWithdrawalRequest, created: bool}
     */
    public function openRequest(Student $student, User $actor, string $initiatedBy): array
    {
        if ($student->status !== Student::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'student' => 'Hanya santri berstatus aktif yang dapat mengajukan keputusan keluar.',
            ]);
        }

        $existing = $this->findOpenRequest($student);
        if ($existing) {
            return ['request' => $existing->load(['student:id,full_name,nis', 'santriUser:id,name', 'waliUser:id,name']), 'created' => false];
        }

        $request = StudentWithdrawalRequest::query()->create([
            'student_id' => $student->id,
            'status' => StudentWithdrawalRequest::STATUS_AWAITING,
            'initiated_by' => $initiatedBy,
            'initiated_by_user_id' => $actor->id,
        ]);

        return ['request' => $request->load(['student:id,full_name,nis', 'santriUser:id,name', 'waliUser:id,name']), 'created' => true];
    }

    public function submitSantriChoice(
        StudentWithdrawalRequest $request,
        User $santriUser,
        string $choice,
        ?string $reason = null,
        ?string $effectiveDate = null,
    ): StudentWithdrawalRequest {
        $this->assertAwaiting($request);
        $this->assertChoice($choice);

        if ($choice === StudentWithdrawalRequest::CHOICE_WITHDRAW && trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan wajib diisi jika memilih keluar pesantren.',
            ]);
        }

        $request->fill([
            'santri_choice' => $choice,
            'santri_user_id' => $santriUser->id,
            'santri_confirmed_at' => now(),
            'santri_reason' => $reason,
        ]);

        if ($choice === StudentWithdrawalRequest::CHOICE_WITHDRAW) {
            $request->reason = $reason;
            $request->effective_date = $effectiveDate;
        }

        $request->save();

        return $this->afterChoiceUpdated($request);
    }

    public function submitWaliChoice(
        StudentWithdrawalRequest $request,
        User $waliUser,
        Guardian $guardian,
        string $choice,
        ?string $reason = null,
        ?string $effectiveDate = null,
    ): StudentWithdrawalRequest {
        $this->assertAwaiting($request);
        $this->assertChoice($choice);

        if ($choice === StudentWithdrawalRequest::CHOICE_WITHDRAW && trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan wajib diisi jika memilih keluar pesantren.',
            ]);
        }

        $request->fill([
            'wali_choice' => $choice,
            'wali_user_id' => $waliUser->id,
            'wali_guardian_id' => $guardian->id,
            'wali_confirmed_at' => now(),
            'wali_reason' => $reason,
        ]);

        if ($choice === StudentWithdrawalRequest::CHOICE_WITHDRAW) {
            $request->reason = $reason ?? $request->reason;
            $request->effective_date = $effectiveDate ?? $request->effective_date;
        }

        $request->save();

        return $this->afterChoiceUpdated($request);
    }

    public function cancel(StudentWithdrawalRequest $request, User $actor): StudentWithdrawalRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'request' => 'Permohonan ini tidak dapat dibatalkan.',
            ]);
        }

        $request->update(['status' => StudentWithdrawalRequest::STATUS_CANCELLED]);
        $request = $request->fresh(['student', 'santriUser', 'waliUser']);
        $this->notifyParties($request, 'cancelled');

        return $request;
    }

    public function approve(StudentWithdrawalRequest $request, User $admin, ?string $adminNotes = null): StudentWithdrawalRequest
    {
        if ($request->status !== StudentWithdrawalRequest::STATUS_PENDING_ADMIN) {
            throw ValidationException::withMessages([
                'request' => 'Hanya permohonan yang menunggu persetujuan admin yang dapat disetujui.',
            ]);
        }

        if ($request->resolved_choice !== StudentWithdrawalRequest::CHOICE_WITHDRAW) {
            throw ValidationException::withMessages([
                'request' => 'Keputusan efektif bukan keluar pesantren.',
            ]);
        }

        DB::transaction(function () use ($request, $admin, $adminNotes) {
            $student = $request->student()->lockForUpdate()->first();
            $this->finalizeWithdrawal($student, $request);

            $request->update([
                'status' => StudentWithdrawalRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);
        });

        $request = $request->fresh(['student', 'santriUser', 'waliUser', 'reviewer:id,name']);
        $this->notifyParties($request, 'approved');

        return $request;
    }

    public function reject(StudentWithdrawalRequest $request, User $admin, string $rejectionReason, ?string $adminNotes = null): StudentWithdrawalRequest
    {
        if ($request->status !== StudentWithdrawalRequest::STATUS_PENDING_ADMIN) {
            throw ValidationException::withMessages([
                'request' => 'Hanya permohonan yang menunggu persetujuan admin yang dapat ditolak.',
            ]);
        }

        $request->update([
            'status' => StudentWithdrawalRequest::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
            'admin_notes' => $adminNotes,
        ]);

        $request = $request->fresh(['student', 'santriUser', 'waliUser', 'reviewer:id,name']);
        $this->notifyParties($request, 'rejected');

        return $request;
    }

    public function resolveEffectiveChoice(StudentWithdrawalRequest $request): string
    {
        if ($request->wali_choice !== null) {
            return $request->wali_choice;
        }

        return (string) $request->santri_choice;
    }

    private function afterChoiceUpdated(StudentWithdrawalRequest $request): StudentWithdrawalRequest
    {
        $request->refresh();

        if (! $request->bothChoicesSubmitted()) {
            $this->notifyCounterpartyPending($request);

            return $request->load(['student:id,full_name,nis', 'santriUser:id,name', 'waliUser:id,name']);
        }

        $resolved = $this->resolveEffectiveChoice($request);
        $request->resolved_choice = $resolved;

        if ($resolved === StudentWithdrawalRequest::CHOICE_CONTINUE) {
            $request->status = StudentWithdrawalRequest::STATUS_CLOSED_CONTINUE;
            $request->save();
            $this->notifyParties($request->fresh(), 'closed_continue');

            return $request->load(['student:id,full_name,nis', 'santriUser:id,name', 'waliUser:id,name']);
        }

        $request->status = StudentWithdrawalRequest::STATUS_PENDING_ADMIN;
        $request->save();
        $this->notifyParties($request->fresh(), 'pending_admin');
        $this->notifyAdminsPending($request);

        return $request->load(['student:id,full_name,nis', 'santriUser:id,name', 'waliUser:id,name']);
    }

    private function finalizeWithdrawal(Student $student, StudentWithdrawalRequest $request): void
    {
        $checkoutDate = $request->effective_date?->toDateString() ?? now()->toDateString();

        DormAssignment::query()
            ->where('student_id', $student->id)
            ->whereNull('checkout_date')
            ->update(['checkout_date' => $checkoutDate]);

        $student->update(['status' => Student::STATUS_KELUAR]);
    }

    private function assertAwaiting(StudentWithdrawalRequest $request): void
    {
        if ($request->status !== StudentWithdrawalRequest::STATUS_AWAITING) {
            throw ValidationException::withMessages([
                'request' => 'Permohonan tidak dalam tahap konfirmasi.',
            ]);
        }
    }

    private function assertChoice(string $choice): void
    {
        if (! in_array($choice, StudentWithdrawalRequest::CHOICES, true)) {
            throw ValidationException::withMessages([
                'choice' => 'Pilihan tidak valid.',
            ]);
        }
    }

    private function notifyCounterpartyPending(StudentWithdrawalRequest $request): void
    {
        if ($request->santri_choice !== null && $request->wali_choice === null) {
            $this->notifyGuardians($request, 'wali_pending');
        }
        if ($request->wali_choice !== null && $request->santri_choice === null) {
            $student = $request->student;
            $student?->user?->notify(new StudentWithdrawalStatusNotification($request, 'santri_pending'));
        }
    }

    private function notifyParties(StudentWithdrawalRequest $request, string $event): void
    {
        $request->student?->user?->notify(new StudentWithdrawalStatusNotification($request, $event));
        $this->notifyGuardians($request, $event);
    }

    private function notifyGuardians(StudentWithdrawalRequest $request, string $event): void
    {
        $request->loadMissing('student.guardians.user');
        $request->student?->guardians
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new StudentWithdrawalStatusNotification($request, $event)));
    }

    private function notifyAdminsPending(StudentWithdrawalRequest $request): void
    {
        User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN_AKADEMIK]))
            ->get()
            ->each(fn (User $user) => $user->notify(new StudentWithdrawalStatusNotification($request, 'admin_pending')));
    }
}
