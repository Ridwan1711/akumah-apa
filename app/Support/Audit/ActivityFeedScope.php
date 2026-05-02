<?php

namespace App\Support\Audit;

use App\Models\Diniyyah\Score;
use App\Models\Diniyyah\TeacherAssignment;
use App\Models\DormAssignment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\LeavePermission;
use App\Models\LessonAttendance;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentViolation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ActivityFeedScope
{
    private function __construct() {}

    /**
     * Narrow [AuditLog] query for the authenticated user and return a short UI description (Bahasa Indonesia).
     */
    public static function apply(Builder $query, User $user): string
    {
        $user->loadMissing([
            'roles:id,name',
            'student:id,user_id',
            'guardian:id,user_id',
            'guardians:id',
        ]);

        if ($user->isSuperAdmin()) {
            return AuditLogModules::scopeDescription($user);
        }

        $modules = AuditLogModules::allowedModuleNames($user);

        if (count($modules) > 0) {
            $query->whereIn('module', $modules);

            return AuditLogModules::scopeDescription($user);
        }

        if ($user->isAdmin()) {
            $query->whereRaw('1 = 0');

            return 'Anda tidak memiliki izin melihat log aktivitas sistem.';
        }

        if ($user->student !== null) {
            self::fillStudentPortfolioBranches($query, [(int) $user->student->id]);

            return 'Menampilkan perubahan data yang menyangkut akun santri Anda.';
        }

        if ($user->hasRole(Role::WALI_SANTRI)) {
            $studentIds = self::waliStudentIds($user);
            $guardianIds = self::waliGuardianIds($user);

            if ($studentIds === [] && $guardianIds === []) {
                $query->whereRaw('1 = 0');

                return 'Belum ada data wali atau santri yang terhubung.';
            }

            $guardianMorph = (new Guardian)->getMorphClass();

            $query->where(function (Builder $outer) use ($studentIds, $guardianIds, $guardianMorph) {
                if ($studentIds !== [] && $guardianIds !== []) {
                    $outer->where(fn (Builder $q) => self::nestedPortfolioBranches($q, $studentIds));
                    $outer->orWhere(fn (Builder $q) => $q->where('auditable_type', $guardianMorph)->whereIn('auditable_id', $guardianIds));
                } elseif ($studentIds !== []) {
                    self::nestedPortfolioBranches($outer, $studentIds);
                } else {
                    $outer->where(fn (Builder $q) => $q->where('auditable_type', $guardianMorph)->whereIn('auditable_id', $guardianIds));
                }
            });

            return 'Menampilkan aktivitas santri Anda dan perubahan data wali.';
        }

        if ($user->hasRole(Role::GURU)) {
            self::applyGuruConstraint($query, $user);

            return 'Menampilkan nilai diniyah yang Anda input, kehadiran pelajaran yang Anda catat, dan aktivitas santri di kelas Anda.';
        }

        $query->whereRaw('1 = 0');

        return '';
    }

    /**
     * @param  list<int>  $studentIds
     */
    public static function fillStudentPortfolioBranches(Builder $query, array $studentIds): void
    {
        self::nestedPortfolioBranches($query, $studentIds);
    }

    /**
     * @param  list<int>  $studentIds
     */
    private static function nestedPortfolioBranches(Builder $query, array $studentIds): void
    {
        $query->where(function (Builder $inner) use ($studentIds) {
            $studentMorph = (new Student)->getMorphClass();
            $scoreMorph = (new Score)->getMorphClass();

            $inner->where(fn (Builder $x) => $x->where('auditable_type', $studentMorph)->whereIn('auditable_id', $studentIds));

            $leaveIds = LeavePermission::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($leaveIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'leavepermission')->whereIn('auditable_id', $leaveIds));
            }

            $vioIds = StudentViolation::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($vioIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'studentviolation')->whereIn('auditable_id', $vioIds));
            }

            $dormIds = DormAssignment::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($dormIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'dormassignment')->whereIn('auditable_id', $dormIds));
            }

            $invIds = Invoice::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($invIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'invoice')->whereIn('auditable_id', $invIds));
            }

            $paymentIds = Payment::query()
                ->whereHas('invoice', fn (Builder $iq) => $iq->whereIn('student_id', $studentIds))
                ->pluck('id');
            if ($paymentIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'payment')->whereIn('auditable_id', $paymentIds));
            }

            $discIds = StudentDiscount::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($discIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'studentdiscount')->whereIn('auditable_id', $discIds));
            }

            $scoreIds = Score::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($scoreIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('auditable_type', $scoreMorph)->whereIn('auditable_id', $scoreIds));
            }

            $lessonAttIds = LessonAttendance::query()->whereIn('student_id', $studentIds)->pluck('id');
            if ($lessonAttIds->isNotEmpty()) {
                $inner->orWhere(fn (Builder $x) => $x->where('module', 'lessonattendance')->whereIn('auditable_id', $lessonAttIds));
            }
        });
    }

    private static function applyGuruConstraint(Builder $query, User $user): void
    {
        $classIds = TeacherAssignment::query()->where('teacher_id', $user->id)->distinct()->pluck('class_id');
        $studentIds = $classIds->isNotEmpty()
            ? Student::query()->whereIn('current_class_id', $classIds)->pluck('id')->unique()->values()->all()
            : [];

        $scoreMorph = (new Score)->getMorphClass();
        $teacherScoreIds = Score::query()->where('teacher_id', $user->id)->pluck('id');
        $markedLessonAttIds = LessonAttendance::query()->where('marked_by', $user->id)->pluck('id');

        $query->where(function (Builder $outer) use ($studentIds, $scoreMorph, $teacherScoreIds, $markedLessonAttIds) {
            $added = false;

            if ($teacherScoreIds->isNotEmpty()) {
                $outer->where(fn (Builder $q) => $q->where('auditable_type', $scoreMorph)->whereIn('auditable_id', $teacherScoreIds));
                $added = true;
            }

            if ($markedLessonAttIds->isNotEmpty()) {
                $lessonClause = fn (Builder $q) => $q->where('module', 'lessonattendance')->whereIn('auditable_id', $markedLessonAttIds);
                $added ? $outer->orWhere($lessonClause) : $outer->where($lessonClause);
                $added = true;
            }

            if ($studentIds !== []) {
                $portfolioClause = fn (Builder $q) => self::nestedPortfolioBranches($q, $studentIds);
                $added ? $outer->orWhere($portfolioClause) : $outer->where($portfolioClause);
                $added = true;
            }

            if (! $added) {
                $outer->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @return list<int>
     */
    private static function waliStudentIds(User $user): array
    {
        $ids = collect();

        foreach ($user->guardians()->get(['guardians.id']) as $guardian) {
            $ids = $ids->merge($guardian->students()->pluck('students.id'));
        }

        $legacy = $user->guardian;
        if ($legacy !== null) {
            $ids = $ids->merge($legacy->students()->pluck('students.id'));
        }

        return $ids->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @return list<int>
     */
    private static function waliGuardianIds(User $user): array
    {
        $ids = collect($user->guardians()->pluck('guardians.id'));

        if ($user->guardian !== null) {
            $ids->push($user->guardian->id);
        }

        return $ids->unique()->map(fn ($id) => (int) $id)->values()->all();
    }
}
